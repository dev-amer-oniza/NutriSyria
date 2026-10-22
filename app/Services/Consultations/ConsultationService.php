<?php

namespace App\Services\Consultations;

use App\Enums\ConsultationStatus;
use App\Exceptions\ConsultationRefused;
use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\ConsultationRequestResource;
use App\Models\ConsultationRequest;
use App\Models\NutritionistProfile;
use App\Models\User;
use App\Notifications\ConsultationNotification;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Booking a nutritionist (the owner's decisions):
 *  - a person sends a short request with their reason and WhatsApp number, agreeing to share
 *    their health record once accepted; one open request or link at a time;
 *  - the nutritionist is told by email and in the panel, and accepts or declines;
 *  - the person is told by email; once accepted, both see each other's WhatsApp;
 *  - an unanswered request expires after 7 days (checked whenever requests are read);
 *  - either side can end the link, which gives the person back their own criteria.
 * The first consultation is free and there is no payment: nothing here charges anything.
 */
class ConsultationService
{
    public const EXPIRES_AFTER_DAYS = 7;

    public function __construct(private readonly PersonalCriteria $criteria) {}

    /** The person's open request or link, if any. */
    public function current(User $client): ?ConsultationRequest
    {
        $this->expireStale();

        return $client->consultationRequests()->open()->with('nutritionist.nutritionistProfile')->latest('id')->first();
    }

    /** @return Collection<int, ConsultationRequest> the person's closed requests, newest first */
    public function history(User $client): Collection
    {
        $this->expireStale();

        return $client->consultationRequests()
            ->whereNotIn('status', ConsultationStatus::OPEN)
            ->with('nutritionist.nutritionistProfile')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /** The accepted link's nutritionist, if any (the one who may set this person's criteria). */
    public function nutritionistOf(User $client): ?User
    {
        return $client->consultationRequests()
            ->where('status', ConsultationStatus::Accepted)
            ->latest('id')
            ->first()?->nutritionist;
    }

    /** Whether the person can send a request now, or why not (the reason as an exception). */
    public function ensureCanRequest(User $client, NutritionistProfile $profile): void
    {
        if ($profile->user_id === $client->getKey()) {
            throw ConsultationRefused::ownProfile();
        }
        if (! $profile->isListed()) {
            throw ConsultationRefused::notAvailable();
        }
        if (! $client->profile?->hasBody() || ! $client->profile->hasActivity()) {
            throw ConsultationRefused::profileFirst();
        }
        if ($this->current($client) !== null) {
            throw ConsultationRefused::alreadyOpen();
        }
    }

    public function request(User $client, NutritionistProfile $profile, string $reason, string $whatsapp): ConsultationRequest
    {
        $this->ensureCanRequest($client, $profile);

        if ($client->phone !== $whatsapp) {
            $client->forceFill(['phone' => $whatsapp])->save();
        }

        $request = new ConsultationRequest;
        $request->forceFill([
            'user_id' => $client->getKey(),
            'nutritionist_id' => $profile->user_id,
            'reason' => $reason,
            'status' => ConsultationStatus::Pending,
            'consented_at' => now(),
            'expires_at' => now()->addDays(self::EXPIRES_AFTER_DAYS),
        ])->save();

        $nutritionist = $profile->user;

        Notification::make()
            ->title("طلب استشارة من {$client->name}")
            ->body(mb_strimwidth($reason, 0, 140, '…'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('عرض الطلبات')
                    ->url(ConsultationRequestResource::getUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($nutritionist);

        $this->mail($nutritionist, ConsultationNotification::requested($request));

        return $request;
    }

    public function cancel(User $client, ConsultationRequest $request): void
    {
        $this->ensureOwnedBy($request, $client);
        $this->ensurePending($request);

        $request->forceFill(['status' => ConsultationStatus::Cancelled, 'responded_at' => now()])->save();
    }

    public function accept(User $nutritionist, ConsultationRequest $request): void
    {
        $this->ensureAddressedTo($request, $nutritionist);
        $this->ensurePending($request);

        $request->forceFill(['status' => ConsultationStatus::Accepted, 'responded_at' => now()])->save();

        $this->mail($request->user, ConsultationNotification::accepted($request));
    }

    public function decline(User $nutritionist, ConsultationRequest $request, ?string $reason = null): void
    {
        $this->ensureAddressedTo($request, $nutritionist);
        $this->ensurePending($request);

        $request->forceFill([
            'status' => ConsultationStatus::Declined,
            'decline_reason' => filled($reason) ? trim($reason) : null,
            'responded_at' => now(),
        ])->save();

        $this->mail($request->user, ConsultationNotification::declined($request));
    }

    /**
     * Ends an accepted link, by the person or their nutritionist. The nutritionist's template
     * and criteria are removed, so the person's own values (or the calculated ones) apply again.
     * The other side is told: the nutritionist in the panel, the person by email with the
     * nutritionist's optional reason.
     */
    public function end(User $by, ConsultationRequest $request, ?string $reason = null): void
    {
        if ($request->user_id !== $by->getKey()) {
            $this->ensureAddressedTo($request, $by);
        }
        if (! $request->refresh()->isAccepted()) {
            throw ConsultationRefused::notAccepted();
        }

        $request->forceFill([
            'status' => ConsultationStatus::Ended,
            'ended_at' => now(),
            'ended_by' => $by->getKey(),
            'end_reason' => filled($reason) ? trim($reason) : null,
        ])->save();

        $this->criteria->clearNutritionist($request->user, $by);

        if ($by->is($request->user)) {
            Notification::make()
                ->title("أنهى {$request->user->name} الارتباط")
                ->body('لم يعد سجله الصحي ومعاييره متاحة لك.')
                ->icon('heroicon-o-link-slash')
                ->sendToDatabase($request->nutritionist);
        } else {
            $this->mail($request->user, ConsultationNotification::endedByNutritionist($request));
        }
    }

    /** Pending requests past their 7 days become expired (no scheduler needed on XAMPP). */
    public function expireStale(): int
    {
        return ConsultationRequest::query()
            ->where('status', ConsultationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update(['status' => ConsultationStatus::Expired->value, 'updated_at' => now()]);
    }

    /** Pending requests waiting for this nutritionist (the panel badge). */
    public function pendingCountFor(User $nutritionist): int
    {
        $this->expireStale();

        return ConsultationRequest::query()
            ->where('nutritionist_id', $nutritionist->getKey())
            ->where('status', ConsultationStatus::Pending)
            ->count();
    }

    private function ensurePending(ConsultationRequest $request): void
    {
        $this->expireStale();

        if ($request->refresh()->status !== ConsultationStatus::Pending) {
            throw ConsultationRefused::notPending();
        }
    }

    private function ensureOwnedBy(ConsultationRequest $request, User $client): void
    {
        abort_unless($request->user_id === $client->getKey(), 404);
    }

    private function ensureAddressedTo(ConsultationRequest $request, User $nutritionist): void
    {
        abort_unless($request->nutritionist_id === $nutritionist->getKey(), 404);
    }

    /** Email is a courtesy: a mail server hiccup must not undo the step itself. */
    public function mail(User $to, ConsultationNotification $notification): void
    {
        rescue(fn () => $to->notify($notification), report: true);
    }
}
