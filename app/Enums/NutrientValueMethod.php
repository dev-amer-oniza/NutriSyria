<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NutrientValueMethod: string implements HasLabel
{
    case Imported = 'imported';
    case Estimated = 'estimated';
    case Measured = 'measured';
    case Laboratory = 'laboratory';
    case Calculated = 'calculated'; // recipe-derived values (dish nutrients from ingredients)

    public function getLabel(): string
    {
        return match ($this) {
            self::Imported => 'مستورد من مصدر خارجي',
            self::Estimated => 'مُقدَّر',
            self::Measured => 'مُقاس',
            self::Laboratory => 'تحليل مخبري',
            self::Calculated => 'محسوب من وصفة',
        };
    }
}
