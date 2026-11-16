<?php

namespace App\Services\Site\Kitchen;

use App\Data\Nutrition\CustomRecipeResult;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\RecipeDraft;
use App\Data\Nutrition\RecipeLine;
use App\Enums\FoodSourceStatus;
use App\Exceptions\RecipeRejected;
use App\Models\FoodSourceRecord;
use App\Models\Product;
use App\Models\User;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Nutrition\RecipeAnalysisResult;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\MealCatalog;
use App\Services\Site\PublicCatalogCache;
use Illuminate\Support\Collection;

/**
 * "اطبخها على طريقتك": a visitor's own version of a Syrian kitchen dish, through the very same
 * analysis as the panel's, and nothing written anywhere (the owner's decisions 1a-6).
 *
 * Kitchen dishes only (restaurant recipes stay secret), approved ingredients only. A fixed
 * number of queries per request: the analysis's own, one for the ingredients' records, and
 * our dish's numbers from the public cache.
 */
class CustomRecipeAnalyzer
{
    public function __construct(
        private readonly RecipeAnalysisService $analysis,
        private readonly MealCatalog $catalog,
        private readonly PublicCatalogCache $cache,
        private readonly MealFitService $fit,
    ) {}

    public function analyze(int $dishId, RecipeDraft $draft, ?User $user): CustomRecipeResult
    {
        $original = $this->catalog->find($dishId);
        if ($original === null || ! $original->platform) {
            throw RecipeRejected::notAKitchenDish();
        }

        $records = $this->approvedRecords($draft);
        $result = $this->analysis->analyzeDraft($draft);
        if ($result->gramsTotal <= 0) {
            throw RecipeRejected::nothingToWeigh();
        }

        // Not weighed (3a): their ingredients lose in cooking what ours lose; what is added
        // after cooking is not cooked, so it counts as it is. One analysis either way.
        $estimated = ! $result->hasWeightAfterCooking();
        if ($estimated) {
            $result = $result->withWeightAfterCooking(round(
                $result->gramsBeforeCooking * $this->cookingYield($dishId) + $result->gramsAddedAfterCooking(), 1,
            ));
        }

        $meal = $this->facts($original, $result, $records);

        return new CustomRecipeResult(
            meal: $meal,
            original: $original,
            weightEstimated: $estimated,
            weightAfterCookingG: (float) $result->weightAfterCookingG,
            unconverted: $result->unconvertedIngredients,
            fit: $this->fit->judge($user, $meal),
        );
    }

    /**
     * Every line must name an approved record: the site never analyses a draft or rejected one.
     *
     * @return Collection<int, FoodSourceRecord>
     */
    private function approvedRecords(RecipeDraft $draft)
    {
        $ids = array_values(array_unique(array_map(fn (RecipeLine $l) => $l->recordId, $draft->lines)));
        $records = FoodSourceRecord::with('food:id,name_ar,allergens,allergens_checked')
            ->whereKey($ids)
            ->where('status', FoodSourceStatus::Active)
            // A dish's own values (type "recipe") are never an ingredient (KitchenIngredientCatalog).
            ->whereHas('dataSource', fn ($q) => $q->where('is_active', true)->where('type', '!=', 'recipe'))
            ->get()
            ->keyBy('id');

        $unknown = array_values(array_diff($ids, $records->keys()->all()));
        if ($unknown !== []) {
            throw RecipeRejected::unknownIngredients($unknown);
        }

        return $records;
    }

    /** Our dish's cooked weight per gram cooked (1.0 when nothing is lost), kept with the public catalog. */
    private function cookingYield(int $dishId): float
    {
        return $this->cache->remember("kitchen.yield.{$dishId}", function () use ($dishId): float {
            $ours = $this->analysis->analyze(Product::findOrFail($dishId));

            return $ours->gramsBeforeCooking > 0 && $ours->hasWeightAfterCooking()
                ? max(0.1, ($ours->weightAfterCookingG - $ours->gramsAddedAfterCooking()) / $ours->gramsBeforeCooking)
                : 1.0;
        });
    }

    /** @param  Collection<int, FoodSourceRecord>  $records */
    private function facts(MealFacts $original, RecipeAnalysisResult $result, $records): MealFacts
    {
        $foods = $records->map(fn (FoodSourceRecord $r) => $r->food)->filter()->unique('id');

        return new MealFacts(
            id: $original->id,
            name: $original->name,
            brand: $original->brand,
            description: null,
            servingGrams: $result->weightAfterCookingG / $result->servings,
            nutrients: collect($result->perServing())
                ->filter(fn (array $row) => $row['amount'] !== null)
                ->mapWithKeys(fn (array $row) => [$row['nutrient']['code'] => (float) $row['amount']])
                ->all(),
            verification: 'calculated',
            image: $original->image,
            allergens: $foods->flatMap(fn ($food) => $food->allergens ?? [])->unique()->values()->all(),
            allergensChecked: $foods->isNotEmpty() && $foods->every(fn ($food) => $food->allergens_checked),
            ingredients: $foods->pluck('name_ar')->values()->all(),
            brandId: $original->brandId,
            categoryId: $original->categoryId,
            platform: true,
            withBread: $original->withBread,
        );
    }
}
