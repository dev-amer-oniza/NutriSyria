<?php

namespace App\Services\Site;

use App\Enums\HealthCondition;
use App\Enums\Sex;

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
     * The six goals (config/site.php goals), in order.
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
        $selectable = array_values(array_map(fn (array $tpl) => $tpl['label'], array_filter($t, fn (array $tpl) => $tpl['selectable'] ?? false)));

        $texts = [
            'weight-loss' => [
                'lead' => 'نحسب احتياجك اليومي من عمرك وطولك ووزنك ونشاطك، ثم ننقص منه بهدوء.',
                'rules' => [
                    'ننقص '.abs($c['goal_adjustment']['lose']).' سعرة من احتياجك اليومي، وهو عجز معتدل يناسب أغلب الناس.',
                    'لا ننزل أبداً تحت '.number_format($c['min_kcal']['female']).' سعرة للمرأة و'.number_format($c['min_kcal']['male']).' للرجل.',
                    'كل وجبة تُقارن بحصتها من يومك ('.$mealShare.'%)، والسعرات والدهون والكربوهيدرات تُحسب حين تتجاوزها.',
                    'إن كانت الوجبة أكبر من حصتك، نقترح عليك وزناً أصغر منها يناسبك.',
                ],
                'how' => 'اختر "إنقاص الوزن" هدفاً حين تُعدّ حسابك.',
                'note' => 'في الحمل والرضاعة وسكري الحمل لا ننقص السعرات أبداً.',
            ],
            'muscle' => [
                'lead' => 'لا نملك قالباً خاصاً لبناء العضلات، ونقول ذلك بصراحة. ما نحسبه:',
                'rules' => [
                    'نضيف '.$c['goal_adjustment']['gain'].' سعرة إلى احتياجك اليومي.',
                    'البروتين هدف بنسبة '.$t['balanced']['macros']['protein'].'% من سعراتك في القالب المتوازن، فيزيد بزيادتها.',
                    'إن قلّ بروتين الوجبة كثيراً عن حصتها، نذكر ذلك ملاحظةً لتكمّله في وجبة أخرى.',
                ],
                'how' => 'اختر "زيادة الوزن" هدفاً حين تُعدّ حسابك. ولرقم بروتين يناسب تمرينك، يحدده لك أخصائي.',
                'note' => null,
            ],
            'diabetes' => [
                'lead' => 'السكري يعني أن الكربوهيدرات توزَّع على الوجبات، لا أن تُمنع.',
                'rules' => [
                    'قالب السكري: كربوهيدرات '.$t['diabetes']['macros']['carbs'].'%، بروتين '.$t['diabetes']['macros']['protein'].'%، دهون '.$t['diabetes']['macros']['fat'].'% من السعرات.',
                    'كربوهيدرات الوجبة الواحدة حتى '.$t['diabetes']['rules']['meal_carbohydrate_g']['value'].' غ، وحتى '.$t['gdm']['rules']['meal_carbohydrate_g']['value'].' غ في سكري الحمل.',
                    'السكريات حتى '.$pct($t['diabetes']['rules']['sugars_g']).' من السعرات. '.$c['criteria']['sugars_g']['note'],
                ],
                'how' => 'أضف "السكري" (أو سكري الحمل، أو مقاومة الإنسولين) إلى سجلك الصحي، فيُختار القالب تلقائياً.',
                'note' => null,
            ],
            'heart' => [
                'lead' => 'للقلب والضغط، الأهم هو الملح والدهون المشبعة.',
                'rules' => [
                    'ارتفاع الضغط: قالب DASH، والصوديوم حتى '.number_format($t['dash']['rules']['sodium_mg']['value']).' ملغ في اليوم بدل '.number_format($c['criteria']['sodium_mg']['default']['value']).'.',
                    'أمراض القلب: DASH أيضاً، والدهون المشبعة حتى '.$pct($c['conditions']['heart_disease']['criteria']['fat_sat_g']).' من السعرات بدل '.$pct($c['criteria']['fat_sat_g']['default']).'.',
                    'ارتفاع الكولسترول: الدهون المشبعة حتى '.$pct($t['dyslipidemia']['rules']['fat_sat_g']).' من السعرات.',
                    'قصور القلب: صوديوم '.number_format($t['chf']['rules']['sodium_mg']['value']).' ملغ، مع نصيحة بمتابعة أخصائي للسوائل.',
                ],
                'how' => 'أضف حالتك إلى سجلك الصحي. وإن لم تكن عندك حالة، يمكنك اختيار قالب DASH بنفسك.',
                'note' => null,
            ],
            'fiber' => [
                'lead' => 'الألياف تشبعك أطول، وتفيد الهضم والسكر والكولسترول.',
                'rules' => [
                    'الهدف '.$c['criteria']['fiber_g']['default']['value'].' غ ألياف لكل 1000 سعرة. لمن يحتاج '.number_format(self::EXAMPLE_KCAL).' سعرة: '.$fiberPerDay.' غ في اليوم، أي نحو '.(int) round($fiberPerDay * $c['meal_share']).' غ في الوجبة.',
                    'الألياف حد أدنى: نقصها في وجبة ملاحظة لتكمّلها في وجبة أخرى، ولا يخفض الدرجة.',
                    'بطاقة كل وجبة تعرض أليافها للحصة.',
                ],
                'how' => 'هذا المعيار مفعّل للجميع، ويمكنك رفعه من "معاييري".',
                'note' => null,
            ],
            'balanced' => [
                'lead' => 'بلا حالة صحية، نبدأ من التوزيع المتوازن المعتمد للبالغين.',
                'rules' => [
                    'القالب المتوازن: كربوهيدرات '.$t['balanced']['macros']['carbs'].'%، بروتين '.$t['balanced']['macros']['protein'].'%، دهون '.$t['balanced']['macros']['fat'].'% من السعرات.',
                    'الدهون المشبعة حتى '.$pct($c['criteria']['fat_sat_g']['default']).' والسكريات حتى '.$pct($c['criteria']['sugars_g']['default']).' من السعرات، والصوديوم حتى '.number_format($c['criteria']['sodium_mg']['default']['value']).' ملغ.',
                    'يمكنك أن تختار بنفسك: '.implode('، أو ', $selectable).'.',
                ],
                'how' => 'هذا ما نستعمله ما لم تضف حالة صحية أو تختر قالباً آخر.',
                'note' => null,
            ],
        ];

        $goals = [];
        foreach (config('site.goals') as $key => $goal) {
            $goals[] = ['key' => $key] + $goal + $texts[$key];
        }

        return $goals;
    }

    /**
     * Every condition we calculate for, in the onboarding's groups, with what it brings.
     *
     * @return list<array{label: string, conditions: list<array{label: string, template: ?string, text: string, specialist: bool}>}>
     */
    public function conditions(): array
    {
        $c = config('nutrition_criteria');

        return array_map(fn (array $group) => [
            'label' => $group['label'],
            'conditions' => array_map(function (HealthCondition $condition) use ($c) {
                $rules = $c['conditions'][$condition->value] ?? [];
                $template = isset($rules['template']) ? $c['templates'][$rules['template']] : null;

                return [
                    'label' => $condition->label(),
                    'template' => $template['label'] ?? null,
                    'text' => $template['description'] ?? $this->ownRules($rules['criteria'] ?? []),
                    'specialist' => isset($rules['see_nutritionist']) || isset($template['see_nutritionist']),
                ];
            }, $group['conditions']),
        ], array_values(HealthCondition::grouped(Sex::Female))); // every condition, women-only ones included
    }

    /** A condition without a template, told by its own limits (e.g. anaemia's iron). */
    private function ownRules(array $criteria): string
    {
        $parts = [];
        foreach ($criteria as $key => $rule) {
            $criterion = config("nutrition_criteria.criteria.{$key}");
            $parts[] = $rule['type'] === 'by_sex'
                ? "{$criterion['label']}: {$rule['female']} {$criterion['unit']} للمرأة و{$rule['male']} للرجل في اليوم."
                : "{$criterion['label']}: {$rule['value']} {$criterion['unit']} في اليوم.";
        }

        return implode(' ', $parts);
    }
}
