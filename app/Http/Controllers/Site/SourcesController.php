<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\References\MedicalKind;
use App\Data\Nutrition\References\ReferenceCard;
use App\Enums\HealthCondition;
use App\Http\Controllers\Controller;
use App\Services\Nutrition\Fit\MealFitCalculator;
use App\Services\Nutrition\References\MedicalReferences;
use App\Services\Site\DataCoverage;
use Illuminate\Contracts\View\View;

/**
 * "المصادر" (step 3b, decision 9a): where the food numbers come from, every medical limit with
 * its references or an honest "قيد المراجعة", and the choices that are ours, not a reference's (5a).
 */
class SourcesController extends Controller
{
    public function __invoke(DataCoverage $coverage): View
    {
        $references = MedicalReferences::fromConfig();
        $rules = config('nutrition_criteria');
        $tolerance = $rules['fit_tolerance'];

        return view('site.pages.sources', [
            'sources' => $coverage->sources(),
            'foods' => $coverage->foods(),
            'references' => $references,
            'general' => $references->general(),
            'templates' => $this->grouped($references->templates(), MedicalReferences::TEMPLATE_GROUPS),
            'conditions' => $this->grouped($references->conditions(), HealthCondition::GROUPS),
            'kinds' => MedicalKind::KINDS,
            'choices' => [
                'الحسابات الشخصية تبدأ من عمر '.$rules['min_age'].'، لأن معادلة السعرات للبالغين.',
                'وجبة المطعم تُقارن بـ'.(int) round($rules['meal_share'] * 100).'% من احتياجك اليومي (الوجبة الرئيسية)، إلا إن حدّد أخصائيّك غداءك في خطته.',
                "درجات المناسبة الأربع: تجاوز حتى {$tolerance['suits']}% «مناسبة جداً»، حتى {$tolerance['moderate']}% «باعتدال»، حتى {$tolerance['unfit']}% «تحتاج انتباهاً»، وأكثر «غير مناسبة لك الآن» (للقيد الصارم وحده).",
                'تجاوز أقل من '.$rules['fit_near_pct'].'% نكتبه «بحدود» لا بنسبة.',
                'ملاحظة النقص (ألياف، بروتين…) تظهر فقط إن كانت الوجبة أقل من '.(100 - $tolerance['moderate']).'% من حصتها.',
                'لا نقترح حصة أصغر من '.(int) round(MealFitCalculator::MIN_PORTION_SHARE * 100).'% من الصحن.',
                // Step 7, batch 1 (decision 2a): ours, a note only.
                'لمن هضمه حساس (الارتجاع، المرارة، عدم تحمّل الدسم، البنكرياس، اليرقان): ملاحظة لطيفة، لا تغيّر الدرجة، إن كان في الوجبة بقوليات وأليافها '.$rules['legume_fibre']['min_fiber_g'].' غ فأكثر.',
            ],
        ]);
    }

    /**
     * @param  list<ReferenceCard>  $cards
     * @param  array<string, string>  $groups
     * @return list<array{label: string, cards: list<ReferenceCard>}>
     */
    private function grouped(array $cards, array $groups): array
    {
        $out = [];
        foreach ($groups as $key => $label) {
            $in = array_values(array_filter($cards, fn ($card) => $card->group === $key));
            if ($in) {
                $out[] = ['label' => $label, 'cards' => $in];
            }
        }

        return $out;
    }
}
