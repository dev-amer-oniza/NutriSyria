<?php

namespace App\Services\Site;

/**
 * What each health goal changes in our calculations, told with the numbers the system really
 * uses (config/nutrition_criteria.php), so the page never drifts from the engine
 * (the owner's decisions 1a 2a 3b 4a). No meal is promised to suit a goal in general: a
 * verdict is always for one person.
 */
class HealthGoalGuide
{
    /** A daily target used only to turn "per 1000 kcal" into grams in an example. */
    private const EXAMPLE_KCAL = 2000;

    /**
     * The goals (config/site.php goals), in order.
     *
     * @return list<array{key: string, icon: string, label: string, lead: string, rules: list<string>, how: string, note: ?string}>
     */
    public function goals(): array
    {
        $c = config('nutrition_criteria');
        $t = $c['templates'];
        $pct = fn (array $rule) => $rule['pct'].'%';
        $mealShare = (int) round($c['meal_share'] * 100);
        $fiberPerDay = $c['criteria']['fiber_g']['default']['value'] * self::EXAMPLE_KCAL / 1000;

        $texts = [
            'weight-loss' => [
                'lead' => 'نحسب احتياجك اليومي من عمرك وطولك ووزنك ونشاطك، ثم ننقص منه بهدوء.',
                'rules' => [
                    'ننقص '.abs($c['goal_adjustment']['lose']).' سعرة من احتياجك اليومي. وإن كان وزنك ضمن الطبيعي، نحسبها لك وننبّهك بلطف ألا تنزل تحت الوزن الصحي لطولك.',
                    'لا ننزل أبداً تحت '.number_format($c['min_kcal']['female']).' سعرة للمرأة و'.number_format($c['min_kcal']['male']).' للرجل.',
                    'كل وجبة تُقارن بحصتها من يومك ('.$mealShare.'%)، والسعرات والدهون والكربوهيدرات تُحسب حين تتجاوزها.',
                    'إن كانت الوجبة أكبر من حصتك، نقترح عليك جزءاً أصغر منها يناسبك.',
                ],
                'how' => 'اختر "إنقاص الوزن" هدفاً حين تُعدّ حسابك.',
                'note' => 'NutriSyria حالياً للبالغين الأصحاء ومن يمارسون الرياضة، لا للحوامل والمرضعات.',
            ],
            'muscle' => [
                'lead' => 'لبناء العضل تحتاج سعرات أكثر قليلاً من حرقك، وبروتيناً كافياً.',
                'rules' => [
                    'نضيف '.$c['goal_adjustment']['gain'].' سعرة إلى احتياجك اليومي، ونقيّم وجباتك عليها. لم نجد لهذا الرقم مرجعاً رسمياً بعد، فهو اختيار منّا نعلنه في صفحة المصادر.',
                    'البروتين هدف بنسبة '.$t['balanced']['macros']['protein'].'% من سعراتك، فيزيد بزيادتها.',
                    'إن قلّ بروتين الوجبة كثيراً عن حصتها من الحد الأدنى لاحتياجك اليومي ('.$c['criteria']['protein_min_g']['default']['female'].' غ للمرأة و'.$c['criteria']['protein_min_g']['default']['male'].' غ للرجل)، نذكر ذلك ملاحظةً لتكمّله في وجبة أخرى.',
                ],
                'how' => 'اختر "زيادة الوزن" هدفاً حين تُعدّ حسابك.',
                'note' => null,
            ],
            'fiber' => [
                'lead' => 'الألياف تشبعك أطول، وتفيد الهضم.',
                'rules' => [
                    'الهدف '.$c['criteria']['fiber_g']['default']['value'].' غ ألياف لكل 1000 سعرة. لمن يحتاج '.number_format(self::EXAMPLE_KCAL).' سعرة: '.$fiberPerDay.' غ في اليوم، أي نحو '.(int) round($fiberPerDay * $c['meal_share']).' غ في الوجبة.',
                    'الألياف حد أدنى: نقصها في وجبة ملاحظة لتكمّلها في وجبة أخرى، ولا يخفض الدرجة.',
                    'بطاقة كل وجبة تعرض أليافها للحصة.',
                ],
                'how' => 'هذا المعيار مفعّل للجميع.',
                'note' => null,
            ],
            'balanced' => [
                'lead' => 'نبدأ من التوزيع المتوازن المعتمد للبالغين.',
                'rules' => [
                    'القالب المتوازن: كربوهيدرات '.$t['balanced']['macros']['carbs'].'%، بروتين '.$t['balanced']['macros']['protein'].'%، دهون '.$t['balanced']['macros']['fat'].'% من السعرات.',
                    'الدهون المشبعة حتى '.$pct($c['criteria']['fat_sat_g']['default']).' من السعرات، والصوديوم حتى '.number_format($c['criteria']['sodium_mg']['default']['value']).' ملغ. والسكريات للمعلومة فقط.',
                ],
                'how' => 'هذا ما نستعمله لكل حساب، ما لم يختر لك أخصائيّك غيره.',
                'note' => null,
            ],
        ];

        $goals = [];
        foreach (config('site.goals') as $key => $goal) {
            $goals[] = ['key' => $key] + $goal + $texts[$key];
        }

        return $goals;
    }
}
