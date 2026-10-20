<?php

namespace App\Services\Site;

/**
 * The home page's personal-fit demo: one meal judged for three example people.
 * Illustrative figures, labelled as an example on the page. It becomes a real
 * meal with computed verdicts once the fit rules (set by the nutritionist) exist.
 */
class FitExample
{
    /** @return array<string, mixed> */
    public function meal(): array
    {
        return [
            'name' => 'فتة باذنجان',
            'brand' => 'مثال توضيحي',
            'portion' => 350,
            'kcal' => 433,
            'macros' => ['protein' => 15, 'carbs' => 46, 'fat' => 21],
            'fiber' => 7,
            'verification' => 'calculated',
            'source' => 'مثال توضيحي لطريقة العرض. في حسابك تُحسب الدرجة لك وحدك من بياناتك.',
            'people' => [
                [
                    'label' => 'يريد خفض وزنه',
                    'who' => 'مثال: رجل، 30 سنة، 80 كغ، نشاط متوسط.',
                    'level' => 3,
                    'reasons' => [
                        'سعراتها 22% من احتياجه اليومي، أكثر قليلاً من وجبة واحدة في خطته.',
                        'دهونها 21 غ، أغلبها من الباذنجان والخبز المقليين.',
                    ],
                    'share' => ['energy' => 22, 'protein' => 13, 'carbs' => 20, 'fat' => 32],
                    'portion' => ['grams' => 260, 'kcal' => 322],
                ],
                [
                    'label' => 'رياضي يريد زيادة وزنه',
                    'who' => 'مثال: رجل، 24 سنة، 70 كغ، نشاط عالٍ.',
                    'level' => 4,
                    'reasons' => [
                        'طاقة جيدة ضمن وجبة متوازنة، 14% من احتياجه اليومي.',
                        'فيها 7 غ ألياف، ويكمّلها مصدر بروتين إضافي.',
                    ],
                    'share' => ['energy' => 14, 'protein' => 10, 'carbs' => 12, 'fat' => 21],
                    'portion' => ['grams' => 350, 'kcal' => 433],
                ],
                [
                    'label' => 'مصابة بالسكري',
                    'who' => 'مثال: امرأة، 52 سنة، 68 كغ، سكري من النوع الثاني.',
                    'level' => 2,
                    'reasons' => [
                        'فيها 46 غ كربوهيدرات في الحصة، أغلبها من الخبز.',
                        'الألياف (7 غ) تبطئ امتصاص السكر قليلاً، والحصة الأصغر أنسب.',
                    ],
                    'share' => ['energy' => 25, 'protein' => 14, 'carbs' => 24, 'fat' => 34],
                    'portion' => ['grams' => 200, 'kcal' => 247],
                ],
            ],
        ];
    }
}
