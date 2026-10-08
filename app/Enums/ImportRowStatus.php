<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ImportRowStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Error = 'error';
    case Committed = 'committed';
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الفحص',
            self::Valid => 'سليم',
            self::Error => 'به خطأ',
            self::Committed => 'محفوظ',
            self::Skipped => 'تم تجاوزه',
        };
    }
}
