<?php

namespace App\Services\Nutrition;

use App\Enums\Allergen;
use App\Enums\HealthCondition;
use App\Enums\Sex;
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

        // Pregnancy, gestational diabetes and menopause only apply to women.
        if ($profile->health_conditions) {
            $sex = Sex::from($body['sex']);
            $profile->health_conditions = array_values(array_filter(
                $profile->health_conditions,
                fn (string $c) => HealthCondition::tryFrom($c)?->appliesTo($sex) ?? false,
            ));
        }

        $profile->save();

        return $profile;
    }

    /** @param  array{activity_level: string, goal: string}  $activity */
    public function saveActivity(User $user, array $activity): UserProfile
    {
        $profile = $this->profileOf($user)->fill($activity);
        $profile->save();

        return $profile;
    }

    /**
     * The optional last step. Anything chosen is kept only with the person's consent
     * (checked by the form); choosing nothing clears it. Either way the set-up is done.
     *
     * @param  list<HealthCondition>  $conditions
     * @param  list<Allergen>  $allergens
     */
    public function saveHealth(User $user, array $conditions, array $allergens): UserProfile
    {
        $profile = $this->profileOf($user);
        $any = $conditions || $allergens;

        $profile->fill([
            'health_conditions' => $any ? array_map(fn ($c) => $c->value, $conditions) : null,
            'allergies' => $any ? array_map(fn ($a) => $a->value, $allergens) : null,
            'health_consent_at' => $any ? ($profile->health_consent_at ?? now()) : null,
            'completed_at' => $profile->completed_at ?? now(),
        ])->save();

        return $profile;
    }

    /** "Delete my health data": conditions, allergies and the consent go; body and goal stay. */
    public function deleteHealth(User $user): void
    {
        $profile = $this->profileOf($user);

        if ($profile->exists) {
            $profile->fill(['health_conditions' => null, 'allergies' => null, 'health_consent_at' => null])->save();
        }
    }
}
