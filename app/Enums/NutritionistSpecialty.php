<?php

namespace App\Enums;

/** What a nutritionist works on (the owner's list). */
enum NutritionistSpecialty: string
{
    case Weight = 'weight';
    case Diabetes = 'diabetes';
    case Heart = 'heart';
    case Kidney = 'kidney';
    case Digestion = 'digestion';
    case Pregnancy = 'pregnancy';
    case Sports = 'sports';
    case Elderly = 'elderly';
    case Vegetarian = 'vegetarian';

    public function label(): string
    {
        return match ($this) {
            self::Weight => 'التخسيس وإدارة الوزن',
            self::Diabetes => 'السكري والتمثيل الغذائي',
            self::Heart => 'القلب والضغط',
            self::Kidney => 'الكلى',
            self::Digestion => 'الجهاز الهضمي',
            self::Pregnancy => 'الحمل والرضاعة',
            self::Sports => 'التغذية الرياضية',
            self::Elderly => 'تغذية كبار السن',
            self::Vegetarian => 'النباتيون',
        };
    }

    /** @return array<string, string> value => label, for selects and filters */
    public static function options(): array
    {
        return array_column(array_map(fn (self $s) => ['v' => $s->value, 'l' => $s->label()], self::cases()), 'l', 'v');
    }
}
