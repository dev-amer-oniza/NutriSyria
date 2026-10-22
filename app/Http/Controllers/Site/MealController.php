<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\MealFacts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meals\MealSearchRequest;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\MealCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The approved meals, and one meal with its verdict for the signed-in person. */
class MealController extends Controller
{
    /** "Suits me": very well (4) or in moderation (3). */
    private const SUITING_LEVEL = 3;

    public function index(MealSearchRequest $request, MealCatalog $catalog, MealFitService $fits): View
    {
        $meals = $catalog->meals(search: $request->search());
        $verdicts = $fits->judgeAll($request->user(), $meals);

        if ($request->onlySuiting() && $verdicts) {
            $meals = array_values(array_filter($meals, fn (MealFacts $m) => $verdicts[$m->id]->level >= self::SUITING_LEVEL));
        }

        return view('site.pages.meals.index', [
            'dishes' => array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $meals),
            'search' => $request->search(),
            'onlySuiting' => $request->onlySuiting(),
            'personal' => $fits->sheetFor($request->user()) !== null,
        ]);
    }

    public function show(Request $request, int $meal, MealCatalog $catalog, MealFitService $fits): View
    {
        $facts = $catalog->find($meal) ?? abort(404);

        return view('site.pages.meals.show', [
            'meal' => $facts,
            'fit' => $fits->judge($request->user(), $facts),
            'personal' => $fits->sheetFor($request->user()) !== null,
        ]);
    }
}
