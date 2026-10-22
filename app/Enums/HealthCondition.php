<?php

namespace App\Enums;

/**
 * Conditions a person can declare. Each brings a diet template and/or limits of its own
 * (config/nutrition_criteria.php 'conditions').
 */
enum HealthCondition: string
{
    // Diabetes and metabolism
    case Diabetes = 'diabetes';
    case GestationalDiabetes = 'gestational_diabetes';
    case InsulinResistance = 'insulin_resistance';
    case HighCholesterol = 'high_cholesterol';
    // Heart and kidneys
    case Hypertension = 'hypertension';
    case HeartDisease = 'heart_disease';
    case HeartFailure = 'heart_failure';
    case Kidney = 'kidney';
    case Dialysis = 'dialysis';
    // Digestion and fat
    case Reflux = 'reflux';
    case GallbladderRemoved = 'gallbladder_removed';
    case FatIntolerance = 'fat_intolerance';
    case Pancreatitis = 'pancreatitis';
    case Celiac = 'celiac';
    case FattyLiver = 'fatty_liver';
    case LiverCirrhosis = 'liver_cirrhosis';
    // Other
    case Gout = 'gout';
    case Anemia = 'anemia';
    case Osteoporosis = 'osteoporosis';
    case PregnantOrBreastfeeding = 'pregnant_or_breastfeeding';
    case Menopause = 'menopause';

    /** Group key => heading, in the order shown. */
    public const GROUPS = [
        'metabolic' => 'السكري والتمثيل الغذائي',
        'heart_kidney' => 'القلب والكلى',
        'digestion' => 'الهضم والدسم',
        'other' => 'حالات أخرى',
    ];

    public function label(): string
    {
        return match ($this) {
            self::Diabetes => 'السكري',
            self::GestationalDiabetes => 'سكري الحمل',
            self::InsulinResistance => 'مقاومة الإنسولين أو تكيّس المبايض',
            self::HighCholesterol => 'ارتفاع الكولسترول والدهون',
            self::Hypertension => 'ارتفاع ضغط الدم',
            self::HeartDisease => 'أمراض القلب',
            self::HeartFailure => 'قصور القلب',
            self::Kidney => 'أمراض الكلى (قبل الغسيل)',
            self::Dialysis => 'غسيل الكلى',
            self::Reflux => 'الارتجاع المريئي (الحرقة)',
            self::GallbladderRemoved => 'استئصال المرارة',
            self::FatIntolerance => 'عدم تحمّل الدسم',
            self::Pancreatitis => 'التهاب البنكرياس',
            self::Celiac => 'السيلياك (حساسية الغلوتين)',
            self::FattyLiver => 'الكبد الدهني',
            self::LiverCirrhosis => 'الكبد الوبائي أو التليّف',
            self::Gout => 'النقرس',
            self::Anemia => 'فقر الدم',
            self::Osteoporosis => 'هشاشة العظام',
            self::PregnantOrBreastfeeding => 'حامل أو مرضعة',
            self::Menopause => 'سن الأمل',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Diabetes, self::GestationalDiabetes, self::InsulinResistance, self::HighCholesterol => 'metabolic',
            self::Hypertension, self::HeartDisease, self::HeartFailure, self::Kidney, self::Dialysis => 'heart_kidney',
            self::Reflux, self::GallbladderRemoved, self::FatIntolerance, self::Pancreatitis, self::Celiac,
            self::FattyLiver, self::LiverCirrhosis => 'digestion',
            default => 'other',
        };
    }

    /** Pregnancy, gestational diabetes and menopause are offered to women only. */
    public function appliesTo(?Sex $sex): bool
    {
        $womenOnly = [self::PregnantOrBreastfeeding, self::GestationalDiabetes, self::Menopause];

        return ! in_array($this, $womenOnly, true) || $sex === Sex::Female;
    }

    /** @return array<string, array{label: string, conditions: list<self>}> the groups, with what applies to this person */
    public static function grouped(?Sex $sex): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $label) {
            $groups[$key] = [
                'label' => $label,
                'conditions' => array_values(array_filter(self::cases(), fn (self $c) => $c->group() === $key && $c->appliesTo($sex))),
            ];
        }

        return $groups;
    }
}
