<?php

namespace App\Enums;

/** The owner's five activity levels; the multipliers live in config/nutrition_criteria.php. */
enum ActivityLevel: string
{
    case Sedentary = 'sedentary';
    case Light = 'light';
    case Moderate = 'moderate';
    case Very = 'very';
    case Extreme = 'extreme';

    public function label(): string
    {
        return match ($this) {
            self::Sedentary => 'خامل',
            self::Light => 'نشاط خفيف',
            self::Moderate => 'نشاط متوسط',
            self::Very => 'نشاط عالٍ',
            self::Extreme => 'نشاط عالٍ جداً',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sedentary => 'عمل مكتبي، وحركة يومية اعتيادية دنيا في البيت أو المكتب، دون تمارين رياضية منتظمة.',
            self::Light => 'تمارين خفيفة أو مشي معتدل من يوم إلى 3 أيام في الأسبوع، أو عمل يتطلب الوقوف والحركة المستمرة دون جهد شاق.',
            self::Moderate => 'تمارين متوسطة الشدة من 3 إلى 5 أيام في الأسبوع.',
            self::Very => 'تمارين شاقة أو تدريب رياضي من 6 إلى 7 أيام في الأسبوع.',
            self::Extreme => 'تدريب رياضي مكثف مرتين يومياً، أو عمل بدني شاق جداً مثل عمال البناء والرياضيين المحترفين.',
        };
    }

    public function factor(): float
    {
        return (float) config("nutrition_criteria.activity_factors.{$this->value}");
    }
}
