<?php

namespace App\Services\Nutrition;

use App\Enums\HealthCondition;
use App\Models\User;
use App\Notifications\SiteNotification;
use App\Services\Consultations\ConsultationService;
use Illuminate\Support\Carbon;

/**
 * Asks, every four weeks, whether a passing condition ("عدم تحمّل الدسم", jaundice) still holds,
 * so the meals stop being judged on a stage the person has left (the owner's decision c).
 * Run on a signed-in page (no scheduler, as for consultations): the clock starts the first time
 * such a condition is seen, and stops when none is left.
 */
class RecoveryReminder
{
    public const EVERY_DAYS = 28;

    public function __construct(private readonly ConsultationService $consultations) {}

    public function check(User $user): void
    {
        $profile = $user->profile;
        if ($profile === null) {
            return;
        }

        $passing = array_values(array_filter(
            array_map(fn ($value) => HealthCondition::tryFrom((string) $value), $profile->health_conditions ?? []),
            fn (?HealthCondition $c) => $c?->isTemporary() ?? false,
        ));

        $since = $profile->recovery_reminded_at;
        if ($passing === []) {
            if ($since !== null) {
                $profile->forceFill(['recovery_reminded_at' => null])->saveQuietly();
            }

            return;
        }
        if ($since === null) {
            $profile->forceFill(['recovery_reminded_at' => now()])->saveQuietly();

            return;
        }
        if (Carbon::parse($since)->addDays(self::EVERY_DAYS)->isFuture()) {
            return;
        }

        $user->notify(SiteNotification::recoveryCheck(
            array_map(fn (HealthCondition $c) => $c->label(), $passing),
            $this->consultations->isManaged($user),
        ));
        $profile->forceFill(['recovery_reminded_at' => now()])->saveQuietly();
    }
}
