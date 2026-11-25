<?php

namespace App\Services\Nutrition;

use App\Enums\Allergen;
use App\Enums\DailyLife;
use App\Models\User;
use App\Models\UserProfile;

/** Saves the account set-up one step at a time, so leaving half way loses nothing. */
class UserProfileService
{
    /** Read fresh (not the cached relation), so a profile is never created twice. */
    public function profileOf(User $user): UserProfile
    {
        $profile = $user->profile()->firstOrNew();
        $user->setRelation('profile', $profile); // the same object, saved a moment later

        return $profile;
    }

    /** @param  array{sex: string, birth_year: int, height_cm: float, weight_kg: float}  $body */
    public function saveBody(User $user, array $body): UserProfile
    {
        $profile = $this->profileOf($user)->fill($body);
        $profile->save();

        return $profile;
    }

    /**
     * The day, the exercise and the goal (any of them; the nutritionist edits the first two).
     * Work days only for a job or study; an empty list of exercise is "none"; the training
     * days in all only with two exercise rows or more.
     *
     * @param  array{daily_life?: string, work_days?: ?int, exercises?: array<array{type: string, times: int|string, minutes: int|string}>, training_days?: int|string|null, goal?: string}  $activity
     */
    public function saveActivity(User $user, array $activity): UserProfile
    {
        if (isset($activity['daily_life'])) {
            $activity['work_days'] = DailyLife::from($activity['daily_life'])->hasWorkDays() ? (int) ($activity['work_days'] ?? 7) : null;
        }
        if (array_key_exists('exercises', $activity)) {
            $activity['exercises'] = array_map(fn (array $e) => [
                'type' => (string) $e['type'], 'times' => (int) $e['times'], 'minutes' => (int) $e['minutes'],
            ], array_values($activity['exercises'])) ?: null;
            // The days in all only mean something for two rows or more.
            $activity['training_days'] = count($activity['exercises'] ?? []) > 1 && filled($activity['training_days'] ?? null)
                ? (int) $activity['training_days'] : null;
        } else {
            unset($activity['training_days']);
        }
        $profile = $this->profileOf($user)->fill($activity);
        $profile->save();

        return $profile;
    }

    /**
     * The last step: allergies. Anything chosen is kept only with the person's consent
     * (checked by the form); choosing nothing clears it. Either way the set-up is done.
     *
     * @param  list<Allergen>  $allergens
     */
    public function saveHealth(User $user, array $allergens): UserProfile
    {
        $profile = $this->profileOf($user);
        $any = $allergens !== [];

        $profile->fill([
            'allergies' => $any ? array_map(fn ($a) => $a->value, $allergens) : null,
            'health_consent_at' => $any ? ($profile->health_consent_at ?? now()) : null,
            'completed_at' => $profile->completed_at ?? now(),
        ])->save();

        return $profile;
    }

    /** "Delete my health data": allergies and the consent go; body and goal stay. */
    public function deleteHealth(User $user): void
    {
        $profile = $this->profileOf($user);

        if ($profile->exists) {
            $profile->fill(['allergies' => null, 'health_consent_at' => null])->save();
        }
    }
}
