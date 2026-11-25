<?php

namespace App\Http\Controllers\Site\Account;

use App\Enums\MealType;
use App\Exceptions\RecipeRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Daily\CustomEatenRequest;
use App\Http\Requests\Daily\FoodRequestForm;
use App\Http\Requests\Daily\LogMealRequest;
use App\Http\Requests\Daily\WeightRequest;
use App\Services\Consultations\ConsultationService;
use App\Services\Daily\DailyLog;
use App\Services\Daily\DailyView;
use App\Services\Daily\FoodRequests;
use App\Services\Daily\Weighing;
use App\Services\Site\Kitchen\CustomRecipeAnalyzer;
use App\Services\Site\MealCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** "يومي": the day's meals and what is left, "أكلتها" from a meal's page, and "ما لقيتها؟". */
class DailyController extends Controller
{
    public function show(Request $request, DailyView $view, ConsultationService $consultations): View
    {
        // ?date=2026-11-24 for a past day of the week; anything odd is today.
        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date'))) {
            $date = rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', $request->query('date'), config('site.daily.timezone')), null, false);
        }

        return view('site.pages.account.daily', [
            ...$view->for($request->user(), $date ?: null),
            'profileReady' => (bool) $request->user()->profile?->hasActivity(),
            // A nutritionist manages the profile: a weighing goes to them (Weighing::record).
            'managed' => $consultations->isManaged($request->user()),
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

    /**
     * "أكلتها" under "نتيجة طبختك": the recipe is analysed again here, from approved ingredients,
     * and the person's own plate is logged with those values (the owner's decision a).
     */
    public function storeCustom(int $dish, CustomEatenRequest $request, CustomRecipeAnalyzer $analyzer, DailyLog $log): JsonResponse
    {
        try {
            $result = $analyzer->analyze($dish, $request->draft(), $request->user());
            $entry = $log->log($request->user(), $result->meal, $request->eaten(), $request->mealType(), $result->meal->name.' (طبختك)');
        } catch (RecipeRejected $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'اختار قديش أكلت من صحنك.'], 422);
        }

        session()->flash('status', "سجّلنا {$entry->name} ({$entry->meal_type->label()}).");

        return response()->json(['redirect' => route('site.daily')]);
    }

    public function destroy(Request $request, int $entry, DailyLog $log): RedirectResponse
    {
        $log->remove($request->user(), $entry);

        return back()->with('status', 'شلناها من يومك.');
    }

    public function weigh(WeightRequest $request, Weighing $weighing): RedirectResponse
    {
        $kg = $request->kilograms();
        $updated = $weighing->record($request->user(), $kg);

        return redirect()->route('site.daily')->with('status', $updated
            ? 'سجّلنا وزنك: '.round($kg, 1).' كغ، وحدّثنا احتياجك عليه.'
            : 'سجّلنا وزنك: '.round($kg, 1).' كغ، ووصل لأخصائيّك ليحدّث ملفك.');
    }

    public function requestFood(FoodRequestForm $request, FoodRequests $requests): RedirectResponse
    {
        $requests->request($request->user(), $request->validated('food_name'), $request->validated('ingredients'),
            MealType::from($request->validated('meal_type')));

        return redirect()->route('site.daily')->with('requested', $request->validated('food_name'));
    }
}
