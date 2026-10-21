<?php

namespace App\Enums;

/** Where a criterion's value comes from; a later one wins over an earlier one. */
enum CriterionSource: string
{
    case Default = 'default';
    case User = 'user';
    case Nutritionist = 'nutritionist';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'محسوب تلقائياً',
            self::User => 'حددته أنت',
            self::Nutritionist => 'حدده أخصائيّك',
        };
    }
}
