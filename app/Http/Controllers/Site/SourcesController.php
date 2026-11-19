<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Site\DataCoverage;
use Illuminate\Contracts\View\View;

/**
 * "المصادر": where the food numbers come from, and the rules that are ours, not a reference's.
 * The page "من أين هذا الحد؟" for the calculation itself comes with the new meal analysis.
 */
class SourcesController extends Controller
{
    public function __invoke(DataCoverage $coverage): View
    {
        $rules = config('nutrition_criteria');
        $tolerance = $rules['fit_tolerance'];

        return view('site.pages.sources', [
            'sources' => $coverage->sources(),
            'foods' => $coverage->foods(),
            'choices' => [
                'الحسابات الشخصية تبدأ من عمر '.$rules['min_age'].'، لأن معادلة السعرات للبالغين.',
                'لزيادة الوزن نضيف '.$rules['goal_adjustment']['gain'].' سعرة إلى احتياجك. لم نجد لهذا الرقم مرجعاً رسمياً بعد، فهو اختيار منّا.',
                'وجبة المطعم تُقارن بـ'.(int) round($rules['meal_share'] * 100).'% من احتياجك اليومي (الوجبة الرئيسية)، إلا إن حدّد أخصائيّك غداءك في خطته.',
                "درجات المناسبة الأربع: تجاوز حتى {$tolerance['suits']}% «مناسبة جداً»، حتى {$tolerance['moderate']}% «باعتدال»، حتى {$tolerance['unfit']}% «تحتاج انتباهاً»، وأكثر «غير مناسبة لك الآن» (لحد وضعه أخصائيّك).",
                'تجاوز أقل من '.$rules['fit_near_pct'].'% نكتبه «بحدود» لا بنسبة.',
                'ملاحظة النقص (ألياف، بروتين…) تظهر فقط إن كانت الوجبة أقل من '.(100 - $tolerance['moderate']).'% من حصتها.',
                'الحصة المناسبة بالصحن: كامل أو ¾ أو ½ أو ¼، مقرّبة للأسفل حتى لا تتجاوز حدودك، ولا نقترح أقل من ربع الصحن.',
            ],
        ]);
    }
}
