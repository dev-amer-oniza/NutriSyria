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
            'image' => asset('images/site/example-fatteh.webp'),
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
                    'headline' => 'السعرات فيها أكثر من حصته في الوجبة.',
                    'reasons' => [
                        'سعراتها 22% من احتياجه اليومي، أكثر قليلاً من وجبة واحدة في خطته.',
                        'دهونها 21 غ، أغلبها من الباذنجان والخبز المقليين.',
                    ],
                    'share' => ['energy' => 22, 'protein' => 13, 'carbs' => 20, 'fat' => 32],
                    'portion' => ['fraction' => 0.75, 'grams' => 263, 'kcal' => 325, 'whole' => false],
                ],
                [
                    'label' => 'رياضي يريد زيادة وزنه',
                    'who' => 'مثال: رجل، 24 سنة، 70 كغ، نشاط عالٍ.',
                    'level' => 4,
                    'headline' => 'ضمن حدوده في هذه الوجبة.',
                    'reasons' => [
                        'طاقة جيدة ضمن وجبة متوازنة، 14% من احتياجه اليومي.',
                        'فيها 7 غ ألياف، ويكمّلها مصدر بروتين إضافي.',
                    ],
                    'share' => ['energy' => 14, 'protein' => 10, 'carbs' => 12, 'fat' => 21],
                    'portion' => ['fraction' => 1.0, 'grams' => 350, 'kcal' => 433, 'whole' => true],
                ],
                [
                    'label' => 'تريد خفض وزنها بنشاط قليل',
                    'who' => 'مثال: امرأة، 45 سنة، 60 كغ، تقضي يومها بالبيت.',
                    'level' => 2,
                    'headline' => 'الدهون فيها أكثر من حصتها في الوجبة.',
                    'reasons' => [
                        'دهونها 21 غ، أكثر من حصة وجبة من يومها، أغلبها من الباذنجان والخبز المقليين.',
                        'سعراتها 433، والحصة الأصغر أنسب لها.',
                    ],
                    'share' => ['energy' => 25, 'protein' => 14, 'carbs' => 24, 'fat' => 34],
                    'portion' => ['fraction' => 0.5, 'grams' => 175, 'kcal' => 217, 'whole' => false],
                ],
            ],
        ];
    }
}
