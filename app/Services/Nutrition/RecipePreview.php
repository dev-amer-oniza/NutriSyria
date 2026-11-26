<?php

namespace App\Services\Nutrition;

use App\Data\Nutrition\RecipeDraft;
use App\Data\Nutrition\RecipeLine;
use App\Filament\Clusters\Brands\Resources\Products\Schemas\IngredientRows;
use Throwable;

/**
 * The live summary beside the product form: what one plate holds so far, from the rows typed
 * on the page (not saved yet). The same calculation as the analysis page (analyzeDraft), so
 * the owner sees at once what a missing row or weight changes. Never official: the nutritionist
 * approves the saved recipe.
 */
class RecipePreview
{
    private const SHOWN = ['energy_kcal', 'protein_g', 'carbohydrate_g', 'fat_g'];

    public function __construct(private readonly RecipeAnalysisService $analysis) {}

    /**
     * @param  array<string, mixed>|null  $recipe  the form's "recipe" state (servings, weight, ingredients)
     * @return array{rows: int, complete: int, unconverted: list<string>, servings: int, hasWeight: bool, perServing: array<string, ?float>}
     */
    public function of(?array $recipe): array
    {
        $lines = [];
        $rows = 0;
        foreach ((array) ($recipe['ingredients'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows++;
            if (empty($row['food_source_record_id']) || empty($row['measure_unit_id']) || (float) ($row['amount'] ?? 0) <= 0) {
                continue;
            }
            $preparation = IngredientRows::preparationId($row['food_source_record_id']);
            $lines[] = new RecipeLine(
                recordId: $preparation ? 0 : (int) $row['food_source_record_id'],
                unitId: (int) $row['measure_unit_id'],
                amount: (float) $row['amount'],
                portionId: ! empty($row['food_portion_id']) ? (int) $row['food_portion_id'] : null,
                addedAfterCooking: (bool) ($row['is_added_after_cooking'] ?? false),
                preparationId: $preparation,
            );
        }

        $servings = max(1, (int) ($recipe['servings'] ?? 1));
        $weight = (float) ($recipe['weight_after_cooking_g'] ?? 0);
        $empty = ['rows' => $rows, 'complete' => count($lines), 'unconverted' => [], 'servings' => $servings,
            'hasWeight' => $weight > 0, 'perServing' => array_fill_keys(self::SHOWN, null)];

        if ($lines === []) {
            return $empty;
        }

        try {
            $result = $this->analysis->analyzeDraft(new RecipeDraft($lines, $servings, null, $weight > 0 ? $weight : null));
        } catch (Throwable) {
            return $empty;
        }

        $perServing = array_fill_keys(self::SHOWN, null);
        foreach ($result->perServing() as $row) {
            $code = $row['nutrient']['code'] ?? null;
            if (in_array($code, self::SHOWN, true)) {
                $perServing[$code] = $row['amount'];
            }
        }

        return [...$empty, 'unconverted' => array_column($result->unconvertedIngredients, 'label'), 'perServing' => $perServing];
    }

    /**
     * A preparation's 100 g, live on its page (تحضيراتي): from its weight once made, or the
     * sum of its foods. For the owner only; products count it through their own analysis.
     *
     * @param  array<string, mixed>|null  $state  the form's state (ingredients, weight_after_g)
     * @return array{rows: int, complete: int, unconverted: list<string>, per100g: array<string, ?float>, grams: ?float}
     */
    public function per100g(?array $state): array
    {
        $lines = [];
        $rows = 0;
        foreach ((array) ($state['ingredients'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows++;
            if (empty($row['food_source_record_id']) || empty($row['measure_unit_id']) || (float) ($row['amount'] ?? 0) <= 0) {
                continue;
            }
            $lines[] = new RecipeLine(
                recordId: (int) $row['food_source_record_id'],
                unitId: (int) $row['measure_unit_id'],
                amount: (float) $row['amount'],
                portionId: ! empty($row['food_portion_id']) ? (int) $row['food_portion_id'] : null,
            );
        }
        $weight = (float) ($state['weight_after_g'] ?? 0);
        $empty = ['rows' => $rows, 'complete' => count($lines), 'unconverted' => [], 'per100g' => array_fill_keys(self::SHOWN, null), 'grams' => null];
        if ($lines === []) {
            return $empty;
        }

        try {
            $result = $this->analysis->analyzeDraft(new RecipeDraft($lines, 1, null, $weight > 0 ? $weight : null));
        } catch (Throwable) {
            return $empty;
        }

        $per100g = array_fill_keys(self::SHOWN, null);
        foreach ($weight > 0 ? $result->per100gAfterCooking() : $result->per100gRaw() as $row) {
            $code = $row['nutrient']['code'] ?? null;
            if (in_array($code, self::SHOWN, true)) {
                $per100g[$code] = $row['amount'];
            }
        }

        return [...$empty, 'unconverted' => array_column($result->unconvertedIngredients, 'label'), 'per100g' => $per100g,
            'grams' => $weight > 0 ? $weight : $result->gramsTotal];
    }
}
