<?php

namespace App\Enums;

/** Where a criterion's value comes from; a later one wins over an earlier one. */
enum CriterionSource: string
{
    case Default = 'default';
    case User = 'user';
    /** A number the person's doctor gave them, typed by the person (step 2, decision 4a). */
    case Doctor = 'doctor';
    case Nutritionist = 'nutritionist';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'محسوب تلقائياً',
            self::User => 'حددته أنت',
            self::Doctor => 'من طبيبك',
            self::Nutritionist => 'حدده أخصائيّك',
        };
    }

    /** Who set a limit, as a reason says it: "والحد الذي حدّده لك طبيبك". Null for ours. */
    public function setter(): ?string
    {
        return match ($this) {
            self::Doctor => 'طبيبك',
            self::Nutritionist => 'أخصائيّك',
            default => null,
        };
    }

    /**
     * How a value set by this source judges a meal (decision 4a): a doctor's or a
     * nutritionist's limit is a strict one; what a person sets for themselves is a target.
     */
    public function kind(string $bound, string $kind): string
    {
        return match (true) {
            $bound === 'min' => 'shortfall',
            $this === self::Doctor, $this === self::Nutritionist => 'strict',
            $this === self::User => 'guidance',
            default => $kind,
        };
    }
}
