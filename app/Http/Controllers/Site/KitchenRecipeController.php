<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\MealSuggestion;
use App\Data\Nutrition\RecipeLine;
use App\Exceptions\RecipeRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\CustomRecipeRequest;
use App\Models\Product;
use App\Services\Nutrition\Fit\MealSuggestions;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\Kitchen\CustomRecipeAnalyzer;
use App\Services\Site\Kitchen\KitchenIngredientCatalog;
use App\Services\Site\MealCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "اطبخها على طريقتك": a kitchen dish's recipe, opened for the visitor to change, and analysed
 * without anything being stored (the owner's decisions 1a-6). Routing only: the work is in
 * CustomRecipeAnalyzer and KitchenIngredientCatalog.
 */
class KitchenRecipeController extends Controller
{
    public function show(Request $request, int $dish, MealCatalog $catalog, RecipeAnalysisService $analysis, KitchenIngredientCatalog $ingredients): View
    {
        $meal = $catalog->find($dish);
        abort_unless($meal !== null && $meal->platform, 404);

        $recipe = $analysis->draftOf(Product::with('recipe')->findOrFail($dish));
        $allowed = $ingredients->ids();
        $ours = [
            // Our recipe, as the form starts (only lines a visitor may use).
            'lines' => array_values(array_map(fn (RecipeLine $l) => [
                'record_id' => $l->recordId, 'unit_id' => $l->unitId, 'amount' => $l->amount,
                'portion_id' => $l->portionId, 'after' => $l->addedAfterCooking,
            ], array_filter($recipe->lines, fn (RecipeLine $l) => isset($allowed[$l->recordId])))),
            'servings' => $recipe->servings,
            'weight_after' => $recipe->weightAfterCookingG,
        ];
        [$start, $applied] = $this->applied($request, $ours, $ingredients);

        return view('site.pages.kitchen-recipe', [
            'meal' => $meal,
            'recipe' => $start,
            'original' => $ours,
            'applied' => $applied,
        ]);
    }

    /**
     * "اطبخها هكذا" (step 6): the suggestion from the meal page applied to our recipe, said in a
     * strip above it. Only a cut of an ingredient the recipe has; anything else opens it as it is.
     *
     * @return array{0: array, 1: ?string} the recipe to start from, and what was applied
     */
    private function applied(Request $request, array $recipe, KitchenIngredientCatalog $ingredients): array
    {
        $changes = [];
        foreach (array_slice((array) $request->query('c', []), 0, 2) as $c) {
            $name = is_array($c) ? mb_substr((string) ($c['i'] ?? ''), 0, 120) : '';
            $keep = is_array($c) ? (float) ($c['k'] ?? -1) : -1.0;
            $valid = array_filter([0.0, ...MealSuggestions::KEEP], fn (float $k) => abs($k - $keep) < 0.001);
            if ($name === '' || $valid === []) {
                return [$recipe, null];
            }
            $changes[] = ['ingredient' => $name, 'after' => (bool) ($c['a'] ?? false), 'keep' => array_values($valid)[0]];
        }
        if ($changes === []) {
            return [$recipe, null];
        }

        $names = array_column($ingredients->all(), 'name', 'id');
        foreach ($changes as $change) {
            $lines = [];
            $found = false;
            foreach ($recipe['lines'] as $line) {
                if (($names[$line['record_id']] ?? null) === $change['ingredient'] && $line['after'] === $change['after']) {
                    $found = true;
                    if ($change['keep'] <= 0) {
                        continue;
                    }
                    $line['amount'] = self::scaled((float) $line['amount'], $change['keep']);
                }
                $lines[] = $line;
            }
            if (! $found) {
                return [$recipe, null];
            }
            $recipe['lines'] = $lines;
        }

        // Less of an ingredient weighs less: the cooked weight is estimated again from ours.
        return [[...$recipe, 'weight_after' => null], (new MealSuggestion($changes, 0, true))->text(false)];
    }

    /** An amount cut to an easy number: whole above 10, halves below (the same in site.js). */
    public static function scaled(float $amount, float $keep): float
    {
        $cut = $amount * $keep;

        return $cut >= 10 ? round($cut) : max(0.5, round($cut * 2) / 2);
    }

    /** The approved ingredients and their units, for the form's search (cached, no query). */
    public function ingredients(KitchenIngredientCatalog $ingredients): JsonResponse
    {
        return response()->json($ingredients->all())->header('Cache-Control', 'private, max-age=300');
    }

    public function analyze(int $dish, CustomRecipeRequest $request, CustomRecipeAnalyzer $analyzer): JsonResponse
    {
        try {
            $result = $analyzer->analyze($dish, $request->draft(), $request->user());
        } catch (RecipeRejected $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'html' => view('site.components.custom-recipe-result', ['result' => $result, 'signedIn' => $request->user() !== null])->render(),
        ]);
    }
}
