<?php

namespace App\Services\Nutrition;

use App\Enums\HealthCondition;
use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Validation\ValidationException;

/**
 * The people who chose the old "الكبد الوبائي أو التليّف" (step 3, decision 8a): it mixed a
 * passing hepatitis A with cirrhosis, and is now three conditions. They are asked, once by the
 * bell and on every page by a strip, to pick the right one or "I don't know yet". Until then
 * the old choice brings no limits: their general needs judge their meals.
 */
class LiverUpdate
{
    /** The choices offered in place of the old one. */
    public const CHOICES = [HealthCondition::HepatitisAcute, HealthCondition::HepatitisChronic, HealthCondition::Cirrhosis];

    public const UNKNOWN = 'unknown';

    public const TITLE = 'حدّثنا حالات الكبد لتكون أدق';

    public function __construct(private readonly UserProfileService $profiles) {}

    public function isPending(?User $user): bool
    {
        // A person may have no profile yet.
        return in_array(HealthCondition::LiverCirrhosis->value, (array) data_get($user, 'profile.health_conditions'), true);
    }

    /** Replaces the old choice with $choice (a HealthCondition value), or drops it for "I don't know yet". */
    public function choose(User $user, string $choice): void
    {
        $picked = $choice === self::UNKNOWN ? null : HealthCondition::tryFrom($choice);
        if ($choice !== self::UNKNOWN && ! in_array($picked, self::CHOICES, true)) {
            throw ValidationException::withMessages(['choice' => 'اختر واحدة من الحالات.']);
        }

        $profile = $this->profiles->profileOf($user);
        $conditions = array_values(array_filter($profile->health_conditions ?? [], fn (string $c) => $c !== HealthCondition::LiverCirrhosis->value));
        if ($picked && ! in_array($picked->value, $conditions, true)) {
            $conditions[] = $picked->value;
        }

        $profile->forceFill(['health_conditions' => $conditions ?: null])->save();
    }

    /** One notice in the bell, the first time the old choice is seen. */
    public function notifyOnce(User $user, bool $managed): void
    {
        if (! $this->isPending($user)
            || $user->notifications()->where('data->title', self::TITLE)->exists()) {
            return;
        }

        $user->notify(SiteNotification::liverUpdate($managed));
    }
}
