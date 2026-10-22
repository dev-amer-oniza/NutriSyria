<?php

namespace App\Enums;

enum SyrianGovernorate: string
{
    case Damascus = 'damascus';
    case RifDimashq = 'rif_dimashq';
    case Aleppo = 'aleppo';
    case Homs = 'homs';
    case Hama = 'hama';
    case Latakia = 'latakia';
    case Tartus = 'tartus';
    case Idlib = 'idlib';
    case DeirEzZor = 'deir_ez_zor';
    case Raqqa = 'raqqa';
    case Hasakah = 'hasakah';
    case Daraa = 'daraa';
    case Sweida = 'sweida';
    case Quneitra = 'quneitra';

    public function label(): string
    {
        return match ($this) {
            self::Damascus => 'دمشق',
            self::RifDimashq => 'ريف دمشق',
            self::Aleppo => 'حلب',
            self::Homs => 'حمص',
            self::Hama => 'حماة',
            self::Latakia => 'اللاذقية',
            self::Tartus => 'طرطوس',
            self::Idlib => 'إدلب',
            self::DeirEzZor => 'دير الزور',
            self::Raqqa => 'الرقة',
            self::Hasakah => 'الحسكة',
            self::Daraa => 'درعا',
            self::Sweida => 'السويداء',
            self::Quneitra => 'القنيطرة',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $g) => ['v' => $g->value, 'l' => $g->label()], self::cases()), 'l', 'v');
    }
}
