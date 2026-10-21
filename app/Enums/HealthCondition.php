<?php

namespace App\Enums;

/** Conditions that change a person's criteria (config/nutrition_criteria.php 'conditions'). */
enum HealthCondition: string
{
    case Hypertension = 'hypertension';
    case Diabetes = 'diabetes';
    case InsulinResistance = 'insulin_resistance';
    case HighCholesterol = 'high_cholesterol';
    case HeartDisease = 'heart_disease';
    case FattyLiver = 'fatty_liver';
    case Kidney = 'kidney';
    case Gout = 'gout';
    case Anemia = 'anemia';
    case Osteoporosis = 'osteoporosis';
    case PregnantOrBreastfeeding = 'pregnant_or_breastfeeding';
    // Digestion and fat: a limit on the fat in a meal, not an ingredient to avoid.
    case GallbladderRemoved = 'gallbladder_removed';
    case FatIntolerance = 'fat_intolerance';
    case Reflux = 'reflux';

    public const GROUP_GENERAL = 'general';

    public const GROUP_DIGESTION = 'digestion';

    public function label(): string
    {
        return match ($this) {
            self::Hypertension => 'ارتفاع ضغط الدم',
            self::Diabetes => 'السكري',
            self::InsulinResistance => 'مقاومة الإنسولين أو تكيّس المبايض',
            self::HighCholesterol => 'ارتفاع الكولسترول',
            self::HeartDisease => 'أمراض القلب',
            self::FattyLiver => 'الكبد الدهني',
            self::Kidney => 'أمراض الكلى',
            self::Gout => 'النقرس',
            self::Anemia => 'فقر الدم',
            self::Osteoporosis => 'هشاشة العظام',
            self::PregnantOrBreastfeeding => 'حامل أو مرضعة',
            self::GallbladderRemoved => 'استئصال المرارة',
            self::FatIntolerance => 'عدم تحمّل الدسم',
            self::Reflux => 'الارتجاع المريئي (الحرقة)',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::GallbladderRemoved, self::FatIntolerance, self::Reflux => self::GROUP_DIGESTION,
            default => self::GROUP_GENERAL,
        };
    }

    /** Pregnancy and breastfeeding are offered to women only. */
    public function appliesTo(?Sex $sex): bool
    {
        return $this !== self::PregnantOrBreastfeeding || $sex === Sex::Female;
    }

    /** @return list<self> the conditions of one group that apply to this person */
    public static function inGroup(string $group, ?Sex $sex): array
    {
        return array_values(array_filter(self::cases(), fn (self $c) => $c->group() === $group && $c->appliesTo($sex)));
    }
}
