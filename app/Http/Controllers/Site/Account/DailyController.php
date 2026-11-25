<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\MealType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Daily\FoodRequestForm;
use App\Http\Requests\Daily\LogMealRequest;
use App\Services\Daily\DailyLog;
use App\Services\Daily\DailyView;
use App\Services\Daily\FoodRequests;
use App\Services\Site\MealCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** "يومي": the day's meals and what is left, "أكلتها" from a meal's page, and "ما لقيتها؟". */
class DailyController extends Controller
{
    public function show(Request $request, DailyView $view): View
    {
        // ?date=2026-11-24 for a past day of the week; anything odd is today.
        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date'))) {
            $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', $request->query('date'), config('site.daily.timezone')), null, false);
        }

        return view('site.pages.account.daily', [
            ...$view->for($request->user(), $date ?: null),
            'profileReady' => (bool) $request->user()->profile?->hasActivity(),
        ]);
    }

    public function store(LogMealRequest $request, int $meal, MealCatalog $catalog, DailyLog $log): RedirectResponse
    {
        $facts = $catalog->find($meal) ?? abort(404);

        try {
            $entry = $log->log($request->user(), $facts, $request->amount(), $request->mealType());
        } catch (InvalidArgumentException) {
            return back()->withErrors(['amount' => 'اختار قديش أكلت منها.']);
        }

        return redirect()->route('site.daily')
            ->with('status', "سجّلنا {$facts->name} ({$entry->meal_type->label()}).");
    }

    public function destroy(Request $request, int $entry, DailyLog $log): RedirectResponse
    {
        $log->remove($request->user(), $entry);

        return back()->with('status', 'شلناها من يومك.');
    }

    public function requestFood(FoodRequestForm $request, FoodRequests $requests): RedirectResponse
    {
        $requests->request($request->user(), $request->validated('food_name'), $request->validated('ingredients'),
            MealType::from($request->validated('meal_type')));

        return redirect()->route('site.daily')->with('requested', $request->validated('food_name'));
    }
}
