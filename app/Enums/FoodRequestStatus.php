<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** A dish a person asked us to add: waiting, added (linked to a meal), or not possible. */
enum FoodRequestStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Added = 'added';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الإضافة',
            self::Added => 'أُضيفت',
            self::Dismissed => 'لن تُضاف',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Added => 'success',
            self::Dismissed => 'gray',
        };
    }
}
