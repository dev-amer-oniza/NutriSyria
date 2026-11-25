<?php

namespace App\Services\Daily;

use App\Data\Daily\WeightCard;
use App\Enums\ConsultationStatus;
use App\Filament\Clusters\Consultations\Resources\Clients\Pages\ViewClient;
use App\Models\ConsultationRequest;
use App\Models\User;
use App\Models\WeightLog;
use App\Notifications\SiteNotification;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * The weekly weight on "يومي". A weighing is kept by day, and becomes the profile's weight so
 * the daily needs follow it, unless a nutritionist manages the profile: then it is only kept,
 * and the nutritionist hears of it (they update the file, as the profile lock requires).
 * A week after the last weighing the site bell reminds the person, once.
 */
class Weighing
{
    public const TOPIC = 'weight';

    /** @return bool whether the profile was updated (false while a nutritionist manages it) */
    public function record(User $user, float $kg): bool
    {
        $range = config('site.daily.weight_kg');
        if ($kg < $range['min'] || $kg > $range['max']) {
            throw new InvalidArgumentException('Weight out of range.');
        }
        $kg = round($kg, 1);

        WeightLog::query()->updateOrInsert(
            ['user_id' => $user->getKey(), 'weighed_on' => DailyLog::now()->toDateString()],
            ['weight_kg' => $kg, 'created_at' => now(), 'updated_at' => now()],
        );

        $link = ConsultationRequest::query()->whereBelongsTo($user)
            ->where('status', ConsultationStatus::Accepted)->with('nutritionist')->latest('id')->first();

        if ($link) {
            Notification::make()
                ->title("سجّل {$user->name} وزنه: {$kg} كغ")
                ->body('حدّث ملفه إن أردت أن تتبعه معاييره وخطته.')
                ->icon('heroicon-o-scale')
                ->info()
                ->actions([Action::make('open')->label('ملف المراجع')->url(ViewClient::getUrl(['record' => $link]))->markAsRead()])
                ->sendToDatabase($link->nutritionist);

            return false;
        }

        if ($user->profile?->hasBody()) {
            $user->profile->forceFill(['weight_kg' => $kg])->save();
        }

        return true;
    }

    /** The card on the day: one query. */
    public function card(User $user): WeightCard
    {
        $logs = WeightLog::query()->whereBelongsTo($user)
            ->orderByDesc('weighed_on')->limit((int) config('site.daily.weight_points'))
            ->get(['weighed_on', 'weight_kg'])->reverse()->values();

        $today = DailyLog::now()->startOfDay();
        $latest = $logs->last();
        $first = $logs->first();
        $weighedOn = $latest ? CarbonImmutable::parse($latest->weighed_on->toDateString(), config('site.daily.timezone')) : null;
        $daysSince = $weighedOn ? (int) $weighedOn->diffInDays($today) : null;

        return new WeightCard(
            current: $latest !== null ? $latest->weight_kg : ($user->profile?->weight_kg !== null ? (float) $user->profile->weight_kg : null),
            weighedOn: $weighedOn,
            points: $logs->pluck('weight_kg')->map(fn ($kg) => (float) $kg)->all(),
            change: $logs->count() > 1 ? round($latest->weight_kg - $first->weight_kg, 1) : null,
            changeWeeks: $logs->count() > 1 ? max(1, (int) round($first->weighed_on->diffInDays($latest->weighed_on) / 7)) : 0,
            due: $daysSince === null || $daysSince >= (int) config('site.daily.weigh_every_days'),
            daysSince: $daysSince,
        );
    }

    /**
     * The bell reminder, a week after the last weighing (or after the profile was set up).
     * Checked once a day per person (a cache key), so a page view costs nothing the rest of
     * the day; a reminder from the last week is never repeated.
     */
    public function remindIfDue(User $user): void
    {
        if (! Cache::add('weigh-check.'.$user->getKey().'.'.DailyLog::now()->toDateString(), true, now()->addDay())) {
            return;
        }

        $profile = $user->profile;
        if (! $profile?->hasBody()) {
            return;
        }

        $every = (int) config('site.daily.weigh_every_days');
        $last = WeightLog::query()->whereBelongsTo($user)->max('weighed_on');
        $since = $last !== null ? CarbonImmutable::parse($last) : CarbonImmutable::parse($profile->created_at);
        if ($since->greaterThan(now()->subDays($every))) {
            return;
        }

        $reminded = $user->notifications()->where('data->topic', self::TOPIC)
            ->where('created_at', '>=', now()->subDays($every))->exists();
        if (! $reminded) {
            $user->notify(SiteNotification::weighReminder());
        }
    }
}
