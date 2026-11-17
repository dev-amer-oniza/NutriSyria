<?php

namespace App\Data\Nutrition\References;

/** The kinds of rule (config/nutrition_criteria.php 'kind'), as people read them. */
final class MedicalKind
{
    /** Key => [label, what it does to a meal]. */
    public const KINDS = [
        'strict' => ['قيد صارم', 'تجاوزه يجعل الوجبة غير مناسبة لك الآن'],
        'tolerance' => ['حد تحمّل', 'انتبه للكمية'],
        'guidance' => ['هدف أو توصية', 'أقسى ما يصل إليه: تحتاج انتباهاً'],
        'shortfall' => ['حد أدنى', 'ملاحظة فقط، لا يخفض الدرجة'],
        'info' => ['للمعلومة', 'يُعرض ولا يدخل في التقييم'],
    ];

    public static function label(string $kind): string
    {
        return self::KINDS[$kind][0] ?? $kind;
    }
}
