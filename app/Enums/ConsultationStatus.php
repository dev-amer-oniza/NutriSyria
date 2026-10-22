<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Where a consultation request stands. Pending and accepted are "open": one at a time. */
enum ConsultationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Ended = 'ended';

    public const OPEN = [self::Pending, self::Accepted];

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار الرد',
            self::Accepted => 'مقبول',
            self::Declined => 'اعتذر الأخصائي',
            self::Cancelled => 'ألغاه المراجع',
            self::Expired => 'انتهت مدته',
            self::Ended => 'انتهى الارتباط',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Declined => 'danger',
            self::Cancelled, self::Expired, self::Ended => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Accepted => Heroicon::OutlinedCheckCircle,
            self::Declined => Heroicon::OutlinedXCircle,
            self::Cancelled => Heroicon::OutlinedArrowUturnLeft,
            self::Expired => Heroicon::OutlinedCalendar,
            self::Ended => Heroicon::OutlinedLink,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, self::OPEN, true);
    }
}
