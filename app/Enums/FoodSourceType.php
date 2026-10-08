<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FoodSourceType: string implements HasLabel
{
    case USDA_FDC        = 'usda_fdc';
    case LOCAL_REFERENCE = 'local_reference';
    case EMFID           = 'emfid';

    public function getLabel(): string
    {
        return match ($this) {
            self::USDA_FDC => 'USDA (FoodData Central)',
            self::LOCAL_REFERENCE => 'مرجع محلي',
            self::EMFID => 'EMFID',
        };
    }
}
