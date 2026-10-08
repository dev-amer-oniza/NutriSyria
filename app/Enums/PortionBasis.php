<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PortionBasis: string implements HasLabel
{
    case Reference = 'reference';
    case Usda = 'usda';
    case Measured = 'measured';
    case Estimated = 'estimated';
    case RestaurantMeasured = 'restaurant_measured';

    public function getLabel(): string
    {
        return match ($this) {
            self::Reference => 'مرجعي',
            self::Usda => 'من USDA',
            self::Measured => 'مقاس فعلياً',
            self::Estimated => 'تقديري',
            self::RestaurantMeasured => 'مقاس في مطعمك',
        };
    }
}
