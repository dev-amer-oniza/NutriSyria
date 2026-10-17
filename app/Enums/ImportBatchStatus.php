<?php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Contracts\HasLabel;

enum ImportBatchStatus: string implements HasLabel, HasColor, HasIcon {
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
            self::Uploaded  => 'gray',
            self::Validated => 'info',
            self::Committed => 'success',
            self::Failed    => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Uploaded  => Heroicon::OutlinedArrowUpTray,
            self::Validated => Heroicon::OutlinedMagnifyingGlass,
            self::Committed => Heroicon::OutlinedCheckCircle,
            self::Failed    => Heroicon::OutlinedXCircle,
        };
    }
}
