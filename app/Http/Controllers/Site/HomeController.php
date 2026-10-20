<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('site.pages.home', [
            'suggestions' => ['مجدّرة', 'فتة', 'شاورما دجاج', 'فول مدمّس', 'تبولة'],
            'featured' => $this->sampleFeatured(),
            'dishes' => $this->sampleDishes(),
        ]);
    }

    /**
     * Example figures for the first design step. The next step replaces these
     * with approved products from the database (a site service).
     */
    private function sampleFeatured(): array
    {
        return [
            'name' => 'فتة باذنجان',
            'brand' => 'مطعم تجريبي',
            'portion' => 350,
            'ingredients' => 9,
            'kcal' => 433,
            'macros' => ['protein' => 15, 'carbs' => 46, 'fat' => 21],
            'fiber' => 7,
            'verification' => 'calculated',
            'approved' => 'تشرين الأول 2026',
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

    private function sampleDishes(): array
    {
        return [
            ['key' => 'mujaddara', 'name' => 'مجدّرة', 'brand' => 'مطعم تجريبي', 'description' => 'عدس وبرغل مع بصل محمّر', 'portion' => 300, 'kcal' => 416, 'protein' => 15, 'carbs' => 62, 'fat' => 12, 'verification' => 'calculated', 'image' => null, 'url' => '#'],
            ['key' => 'tabbouleh', 'name' => 'تبولة', 'brand' => 'مطعم تجريبي', 'description' => 'بقدونس وبرغل وبندورة بزيت الزيتون', 'portion' => 200, 'kcal' => 187, 'protein' => 4, 'carbs' => 18, 'fat' => 12, 'verification' => 'calculated', 'image' => null, 'url' => '#'],
            ['key' => 'kibbeh-labanieh', 'name' => 'كبة لبنية', 'brand' => 'مطعم تجريبي', 'description' => 'كبة بالبرغل واللحم في لبن مطبوخ', 'portion' => 350, 'kcal' => 446, 'protein' => 24, 'carbs' => 38, 'fat' => 22, 'verification' => 'estimated', 'image' => null, 'url' => '#'],
            ['key' => 'foul', 'name' => 'فول مدمّس', 'brand' => 'مطعم تجريبي', 'description' => 'فول بالليمون والثوم وزيت الزيتون', 'portion' => 250, 'kcal' => 310, 'protein' => 16, 'carbs' => 34, 'fat' => 12, 'verification' => 'calculated', 'image' => null, 'url' => '#'],
        ];
    }
}
