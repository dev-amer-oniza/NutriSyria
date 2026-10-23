<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Site\DataCoverage;
use Illuminate\Contracts\View\View;

/** "How we verify": where every number comes from, how a meal is calculated and judged. */
class MethodController extends Controller
{
    public function __invoke(DataCoverage $coverage): View
    {
        $tolerance = config('nutrition_criteria.fit_tolerance');

        return view('site.pages.method', [
            'sources' => $coverage->sources(),
            'foods' => $coverage->foods(),
            'proxies' => $coverage->proxies(),
            'mealShare' => (int) round(config('nutrition_criteria.meal_share') * 100),
            // The four levels and the overshoot each allows (MealFitCalculator::level).
            'fitLevels' => [
                4 => "لا تتجاوز أي حد من حدودك بأكثر من {$tolerance['suits']}%.",
                3 => "تتجاوز حداً بأكثر من {$tolerance['suits']}% وحتى {$tolerance['moderate']}%.",
                2 => "تتجاوز حداً بأكثر من {$tolerance['moderate']}% وحتى {$tolerance['unfit']}%.",
                1 => "تتجاوز حداً بأكثر من {$tolerance['unfit']}%، أو فيها مسبّب حساسية عندك.",
            ],
        ]);
    }
}
