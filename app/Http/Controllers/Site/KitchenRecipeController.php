<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\RecipeLine;
use App\Exceptions\RecipeRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\CustomRecipeRequest;
use App\Models\Product;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\Kitchen\CustomRecipeAnalyzer;
use App\Services\Site\Kitchen\KitchenIngredientCatalog;
use App\Services\Site\MealCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

/**
 * "اطبخها على طريقتك": a kitchen dish's recipe, opened for the visitor to change, and analysed
 * without anything being stored (the owner's decisions 1a-6). Routing only: the work is in
 * CustomRecipeAnalyzer and KitchenIngredientCatalog.
 */
class KitchenRecipeController extends Controller
{
    public function show(int $dish, MealCatalog $catalog, RecipeAnalysisService $analysis, KitchenIngredientCatalog $ingredients): View
    {
        $meal = $catalog->find($dish);
        abort_unless($meal !== null && $meal->platform, 404);

        $recipe = $analysis->draftOf(Product::with('recipe')->findOrFail($dish));
        $allowed = $ingredients->ids();

        return view('site.pages.kitchen-recipe', [
            'meal' => $meal,
            'recipe' => [
                // Our recipe, as the form starts (only lines a visitor may use).
                'lines' => array_values(array_map(fn (RecipeLine $l) => [
                    'record_id' => $l->recordId, 'unit_id' => $l->unitId, 'amount' => $l->amount,
                    'portion_id' => $l->portionId, 'after' => $l->addedAfterCooking,
                ], array_filter($recipe->lines, fn (RecipeLine $l) => isset($allowed[$l->recordId])))),
                'servings' => $recipe->servings,
                'weight_after' => $recipe->weightAfterCookingG,
            ],
        ]);
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
