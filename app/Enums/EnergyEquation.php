<?php

namespace App\Enums;

/**
 * Resting energy (BMR) formulas a nutritionist may choose in a nutrition plan (decision 1a).
 * Mifflin-St Jeor is the default everywhere else; Katch-McArdle needs the body fat share.
 */
enum EnergyEquation: string
{
    case Mifflin = 'mifflin';
    case HarrisBenedict = 'harris_benedict';
    case KatchMcArdle = 'katch_mcardle';

    public function label(): string
    {
        return match ($this) {
            self::Mifflin => 'Mifflin-St Jeor',
            self::HarrisBenedict => 'Harris-Benedict (المعدّلة)',
            self::KatchMcArdle => 'Katch-McArdle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mifflin => 'الأدق لأغلب البالغين، وهي المعتمدة تلقائياً في المنصة.',
            self::HarrisBenedict => 'نسخة Roza وShizgal 1984، تعطي عادة رقماً أعلى قليلاً.',
            self::KatchMcArdle => 'من الكتلة الخالية من الدهون، فتحتاج نسبة الدهون في الجسم.',
        };
    }

    public function needsBodyFat(): bool
    {
        return $this === self::KatchMcArdle;
    }

    /** Resting energy in kcal a day; null when Katch-McArdle has no body fat share. */
    public function bmr(Sex $sex, int $age, float $heightCm, float $weightKg, ?float $bodyFatPct = null): ?float
    {
        $female = $sex === Sex::Female;

        return match ($this) {
            self::Mifflin => 10 * $weightKg + 6.25 * $heightCm - 5 * $age + ($female ? -161 : 5),
            self::HarrisBenedict => $female
                ? 447.593 + 9.247 * $weightKg + 3.098 * $heightCm - 4.330 * $age
                : 88.362 + 13.397 * $weightKg + 4.799 * $heightCm - 5.677 * $age,
            self::KatchMcArdle => $bodyFatPct === null ? null : 370 + 21.6 * $weightKg * (1 - $bodyFatPct / 100),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $e) => ['k' => $e->value, 'v' => $e->label()], self::cases()), 'v', 'k');
    }
}
