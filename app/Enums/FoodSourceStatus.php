<?php
namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status of a food_source_record. Matches decisions-and-principles.md: string column +
 * PHP enum cast, never a DB enum, so a new status is one enum case instead of an ALTER.
 */
enum FoodSourceStatus: string implements HasLabel, HasColor, HasIcon {
    case Active        = 'active';
    case Superseded    = 'superseded';
    case Rejected      = 'rejected';
    case PendingReview = 'pending_review';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active        => 'معتمد',
            self::Superseded    => 'استُبدل بمصدر أحدث',
            self::Rejected      => 'مرفوض',
            self::PendingReview => 'قيد المراجعة',
        };
    }
    public function getColor(): string
    {
        return match ($this) {
            self::Active        => 'success',
            self::PendingReview => 'warning',
            self::Rejected      => 'danger',
            self::Superseded    => 'gray',   // replaced by newer data, not an error
        };
    }

    /** The same shape wherever a status shows, so it reads even without color. */
    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Active        => Heroicon::OutlinedCheckCircle,
            self::PendingReview => Heroicon::OutlinedClock,
            self::Rejected      => Heroicon::OutlinedXCircle,
            self::Superseded    => Heroicon::OutlinedArrowPath,
        };
    }
}
