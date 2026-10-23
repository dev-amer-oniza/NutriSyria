<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Http\Controllers\Controller;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\FitPanel;
use App\Services\Site\MealCatalog;
use App\Services\Site\RestaurantDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** Search chips when there are not enough approved meals to suggest. */
    private const FALLBACK_SUGGESTIONS = ['مجدّرة', 'فتة', 'شاورما دجاج', 'فول مدمّس', 'تبولة'];

    /** "Meals that suit you": up to six, very well (4) then in moderation (3) (decision 3a). */
    private const SUITS_SHOWN = 6;

    private const SUITS_LEVEL = 3;

    private const RESTAURANTS_SHOWN = 6;

    public function __invoke(Request $request, MealCatalog $catalog, MealFitService $fits, FitPanel $panel, RestaurantDirectory $restaurants): View
    {
        $meals = $catalog->meals();
        $verdicts = $fits->judgeAll($request->user(), $meals);
        $latest = array_slice($meals, 0, 8);
        $dishes = array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $latest);

        return view('site.pages.home', [
            'dishes' => $dishes,
            'suits' => $fits->sheetFor($request->user()) ? $this->suits($meals, $verdicts) : null,
            'restaurants' => $restaurants->all(limit: self::RESTAURANTS_SHOWN),
            'suggestions' => $this->suggestions($dishes),
            // The newest meal judged for a signed-in person with criteria; else the example.
            'featured' => $panel->for($latest[0] ?? null, isset($latest[0]) ? ($verdicts[$latest[0]->id] ?? null) : null),
        ]);
    }

    /**
     * @param  list<MealFacts>  $meals  newest first
     * @param  array<int, MealFit>  $verdicts
     * @return list<array<string, mixed>> the best suiting meals as cards (newest first within a level)
     */
    private function suits(array $meals, array $verdicts): array
    {
        $suiting = array_filter($meals, fn (MealFacts $m) => $verdicts[$m->id]->level >= self::SUITS_LEVEL);
        usort($suiting, fn (MealFacts $a, MealFacts $b) => $verdicts[$b->id]->level <=> $verdicts[$a->id]->level);

        return array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id]), array_slice($suiting, 0, self::SUITS_SHOWN));
    }

    /** @param list<array{name: string}> $dishes */
    private function suggestions(array $dishes): array
    {
        $names = array_values(array_unique(array_column($dishes, 'name')));

        return count($names) >= 3 ? array_slice($names, 0, 5) : self::FALLBACK_SUGGESTIONS;
    }
}
