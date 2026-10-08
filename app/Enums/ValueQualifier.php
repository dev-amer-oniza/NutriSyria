<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ValueQualifier: string implements HasLabel
{
    case Trace = 'trace';
    case BelowLod = 'below_lod';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trace => 'أثر (كمية ضئيلة جداً)',
            self::BelowLod => 'أقل من حد الكشف',
        };
    }
}
