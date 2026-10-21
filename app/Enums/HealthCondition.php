<?php

namespace App\Enums;

/** Conditions that change a person's criteria (config/nutrition_criteria.php 'conditions'). */
enum HealthCondition: string
{
    case Hypertension = 'hypertension';
    case Diabetes = 'diabetes';
    case HighCholesterol = 'high_cholesterol';
    case Kidney = 'kidney';
    case PregnantOrBreastfeeding = 'pregnant_or_breastfeeding';

    public function label(): string
    {
        return match ($this) {
            self::Hypertension => 'ارتفاع ضغط الدم',
            self::Diabetes => 'السكري',
            self::HighCholesterol => 'ارتفاع الكولسترول',
            self::Kidney => 'أمراض الكلى',
            self::PregnantOrBreastfeeding => 'حامل أو مرضعة',
        };
    }

    /** Pregnancy and breastfeeding are offered to women only. */
    public function appliesTo(?Sex $sex): bool
    {
        return $this !== self::PregnantOrBreastfeeding || $sex === Sex::Female;
    }
}
