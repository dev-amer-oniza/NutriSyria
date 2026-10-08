<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Country: string implements HasLabel
{
    // Priority set: Levant / MENA (primary domain focus) + major nutrition-source countries.
    // Backed by ISO 3166-1 alpha-2 codes — fits `country` column, standard, extensible.

    case SYRIA         = 'SY';
    case LEBANON       = 'LB';
    case JORDAN        = 'JO';
    case PALESTINE     = 'PS';
    case IRAQ          = 'IQ';
    case EGYPT         = 'EG';
    case SAUDI_ARABIA  = 'SA';
    case UAE           = 'AE';
    case KUWAIT        = 'KW';
    case QATAR         = 'QA';
    case BAHRAIN       = 'BH';
    case OMAN          = 'OM';
    case YEMEN         = 'YE';
    case TURKEY        = 'TR';
    case IRAN          = 'IR';
    case LIBYA         = 'LY';
    case TUNISIA       = 'TN';
    case ALGERIA       = 'DZ';
    case MOROCCO       = 'MA';
    case SUDAN         = 'SD';

    // Major external food-composition data sources
    case USA           = 'US';
    case UK            = 'GB';
    case GERMANY       = 'DE';
    case FRANCE        = 'FR';
    case CANADA        = 'CA';
    case AUSTRALIA     = 'AU';

    public function getLabel(): string
    {
        return match ($this) {
            self::SYRIA => 'سوريا',
            self::LEBANON => 'لبنان',
            self::JORDAN => 'الأردن',
            self::PALESTINE => 'فلسطين',
            self::IRAQ => 'العراق',
            self::EGYPT => 'مصر',
            self::SAUDI_ARABIA => 'السعودية',
            self::UAE => 'الإمارات',
            self::KUWAIT => 'الكويت',
            self::QATAR => 'قطر',
            self::BAHRAIN => 'البحرين',
            self::OMAN => 'عمان',
            self::YEMEN => 'اليمن',
            self::TURKEY => 'تركيا',
            self::IRAN => 'إيران',
            self::LIBYA => 'ليبيا',
            self::TUNISIA => 'تونس',
            self::ALGERIA => 'الجزائر',
            self::MOROCCO => 'المغرب',
            self::SUDAN => 'السودان',
            self::USA => 'الولايات المتحدة',
            self::UK => 'المملكة المتحدة',
            self::GERMANY => 'ألمانيا',
            self::FRANCE => 'فرنسا',
            self::CANADA => 'كندا',
            self::AUSTRALIA => 'أستراليا',
        };
    }
}
