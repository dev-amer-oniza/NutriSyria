<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Two kinds of healthy-food places on the platform (the owner's decision): a restaurant you
 * sit in and order from, or a brand that sells its meals (delivery, boxes). Only how the site
 * shows them differs; the panel, recipes and approval are the same.
 *
 * The owner says which at sign-up (users.brand_kind); the admin sets the brand's own kind.
 */
enum BrandKind: string implements HasColor, HasLabel
{
    case Restaurant = 'restaurant';
    case Brand = 'brand';

    public function getLabel(): string
    {
        return match ($this) {
            self::Restaurant => 'مطعم',
            self::Brand => 'براند',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Restaurant => 'مطاعم',
            self::Brand => 'براندات',
        };
    }

    /** Under the choice at sign-up. */
    public function hint(): string
    {
        return match ($this) {
            self::Restaurant => 'محل بيستقبل الناس وعنده منيو',
            self::Brand => 'علامة بتبيع وجباتها، بالتوصيل أو بالمحلات',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Restaurant => 'primary',
            self::Brand => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $k) => [$k->value => $k->getLabel()])->all();
    }
}
