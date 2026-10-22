<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\MealFacts;
use App\Http\Controllers\Controller;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\FitPanel;
use App\Services\Site\MealCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Search chips when there are not enough approved meals to suggest. */
    private const FALLBACK_SUGGESTIONS = ['مجدّرة', 'فتة', 'شاورما دجاج', 'فول مدمّس', 'تبولة'];

    public function __invoke(Request $request, MealCatalog $catalog, MealFitService $fits, FitPanel $panel): View
    {
        $meals = $catalog->meals(8);
        $verdicts = $fits->judgeAll($request->user(), $meals);
        $dishes = array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $meals);

        return view('site.pages.home', [
            'dishes' => $dishes,
            'suggestions' => $this->suggestions($dishes),
            // The newest meal judged for a signed-in person with criteria; else the example.
            'featured' => $panel->for($meals[0] ?? null, isset($meals[0]) ? ($verdicts[$meals[0]->id] ?? null) : null),
        ]);
    }

    /** @param list<array{name: string}> $dishes */
    private function suggestions(array $dishes): array
    {
        $names = array_values(array_unique(array_column($dishes, 'name')));

        return count($names) >= 3 ? array_slice($names, 0, 5) : self::FALLBACK_SUGGESTIONS;
    }
}
