<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConfidenceLevel: string implements HasLabel
{
    case Calculated = 'calculated';
    case Reference = 'reference';
    case LocalReference = 'local_reference';
    case Measured = 'measured';
    case Reviewed = 'reviewed';
    case Verified = 'verified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Calculated => 'محسوب',
            self::Reference => 'مرجعي',
            self::LocalReference => 'مرجعي محلي',
            self::Measured => 'مقاس',
            self::Reviewed => 'مراجَع',
            self::Verified => 'موثّق',
        };
    }
}
