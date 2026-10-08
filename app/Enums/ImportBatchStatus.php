<?php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ImportBatchStatus: string implements HasLabel, HasColor {
    case Uploaded  = 'uploaded';
    case Validated = 'validated';
    case Committed = 'committed';
    case Failed    = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Uploaded  => 'مرفوعة',
            self::Validated => 'مفحوصة',
            self::Committed => 'محفوظة (قيد المراجعة)',
            self::Failed    => 'فشلت',
        };
    }
    public function getColor(): string
    {
        return match ($this) {
            self::Uploaded, self::Validated => 'gray',
            self::Committed => 'success',
            self::Failed    => 'danger',
        };
    }
}
