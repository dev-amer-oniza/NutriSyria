<?php

namespace App\Services\Daily;

use App\Auth\Access;
use App\Enums\FoodRequestStatus;
use App\Enums\MealType;
use App\Filament\Clusters\Kitchen\Resources\FoodRequests\FoodRequestResource;
use App\Models\FoodRequest;
use App\Models\MealLog;
use App\Models\User;
use App\Notifications\SiteNotification;
use App\Services\Import\ArabicNormalizer;
use App\Services\Site\MealCatalog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "ما لقيتها؟": a dish someone ate that is not on the platform. They send its name and what
 * is in it; it shows on their day without values (never guessed: the owner's decision), and
 * the admin sees it under "أكلات مطلوبة". Requests for the same dish (same name, Arabic
 * spelling aside) are answered together, and everyone who asked hears back in the site bell.
 */
class FoodRequests
{
    public function __construct(private readonly MealCatalog $catalog) {}

    public function request(User $user, string $name, string $ingredients, MealType $type): FoodRequest
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return DB::transaction(function () use ($user, $name, $ingredients, $type) {
            $request = new FoodRequest;
            $request->forceFill([
                'user_id' => $user->getKey(),
                'name' => $name,
                'name_key' => ArabicNormalizer::normalize($name),
                'ingredients' => trim($ingredients),
                'status' => FoodRequestStatus::Pending,
            ])->save();

            $log = new MealLog;
            $log->forceFill([
                'user_id' => $user->getKey(),
                'eaten_on' => DailyLog::now()->toDateString(),
                'eaten_at' => now(),
                'meal_type' => $type,
                'food_request_id' => $request->getKey(),
                'name' => $name,
            ])->save();

            $this->tellAdmins($request);

            return $request;
        });
    }

    /** The admin added the dish: every request for it points there, and each person hears. */
    public function resolve(FoodRequest $request, int $productId, User $admin): int
    {
        $meal = $this->catalog->find($productId)
            ?? throw new InvalidArgumentException('The meal is not public yet: approve it first.');

        $same = $this->samePending($request);
        foreach ($same as $one) {
            $one->forceFill([
                'status' => FoodRequestStatus::Added,
                'product_id' => $meal->id,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => now(),
            ])->save();
            $one->user->notify(SiteNotification::foodAdded($one->name, $meal->name, route('site.meals.show', $meal->id, absolute: false)));
        }

        return $same->count();
    }

    /** It cannot be added: each person is told, with the admin's reason if given. */
    public function dismiss(FoodRequest $request, User $admin, ?string $note = null): int
    {
        $note = filled($note) ? trim($note) : null;
        $same = $this->samePending($request);
        foreach ($same as $one) {
            $one->forceFill([
                'status' => FoodRequestStatus::Dismissed,
                'admin_note' => $note,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => now(),
            ])->save();
            $one->user->notify(SiteNotification::foodDismissed($one->name, $note));
        }

        return $same->count();
    }

    /**
     * The admin removes requests from the list. What people logged stays on their day
     * ("غير محسوبة"): only its link to the request goes, so their diary is never changed.
     *
     * @param  iterable<FoodRequest>  $requests
     */
    public function delete(iterable $requests): int
    {
        $ids = collect($requests)->map(fn (FoodRequest $r) => $r->getKey())->all();

        return DB::transaction(function () use ($ids) {
            MealLog::query()->whereIn('food_request_id', $ids)->update(['food_request_id' => null]);

            return FoodRequest::query()->whereKey($ids)->delete();
        });
    }

    public function pendingCount(): int
    {
        return FoodRequest::query()->where('status', FoodRequestStatus::Pending)->count();
    }

    /** @return Collection<int, FoodRequest> */
    private function samePending(FoodRequest $request)
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException('This request was answered already.');
        }

        return FoodRequest::query()->with('user')
            ->where('status', FoodRequestStatus::Pending)
            ->where('name_key', $request->name_key)
            ->get();
    }

    private function tellAdmins(FoodRequest $request): void
    {
        $admins = User::role(Access::SUPER_ADMIN)->where('is_active', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title("أكلة مطلوبة: {$request->name}")
            ->body(mb_strimwidth($request->ingredients, 0, 140, '…'))
            ->icon('heroicon-o-inbox-arrow-down')
            ->info()
            ->actions([
                Action::make('open')
                    ->label('الأكلات المطلوبة')
                    ->url(FoodRequestResource::getUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }
}
