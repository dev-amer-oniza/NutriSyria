<?php

namespace App\Services\Nutrition;

use App\Enums\ConfidenceLevel;
use App\Enums\NutrientValueMethod;
use App\Models\DataSource;
use App\Models\FoodNutrientValue;
use App\Models\Nutrient;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Database-aware wrapper around the pure RecipeCalculator. Analyze (read-only, safe to call
 * anytime) is separate from Approve (writes the official value) — same "preview, then
 * confirm" shape as the import pipeline's validate()/commit()/approve() split.
 *
 * Scoped to brand products for now, as agreed — a future AnalyzeGeneralRecipeService for
 * dishes will share RecipeCalculator/GramsConverter but have its own thin DB wrapper, since
 * general recipes live in a different section with their own screen.
 */
final class RecipeAnalysisService
{
    public function __construct(
        private readonly RecipeCalculator $calculator = new RecipeCalculator(),
        private readonly GramsConverter $gramsConverter = new GramsConverter(),
    ) {
    }

    public function analyze(Product $product): RecipeAnalysisResult
    {
        $recipe = $product->recipe;
        if (! $recipe) {
            throw new RuntimeException("Product #{$product->id} has no recipe row (should be auto-created on product creation).");
        }

        $ingredientModels = $recipe->ingredients()->with(['ingredientRecord.food'])->get();
        if ($ingredientModels->isEmpty()) {
            throw new RuntimeException('الوصفة لا تحتوي أي مكوّنات بعد.');
        }

        $sourceRecordIds = $ingredientModels->pluck('food_source_record_id')->unique()->values();

        $valuesByRecord = FoodNutrientValue::query()
            ->whereIn('food_source_record_id', $sourceRecordIds)
            ->whereNotNull('amount_per_100g')
            ->get()
            ->groupBy('food_source_record_id');

        $nutrientDefs = Nutrient::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (Nutrient $n) => [
                'id' => $n->id, 'code' => $n->code, 'name_ar' => $n->name_ar,
                'unit' => $n->unit, 'is_core' => $n->is_core, 'display_order' => $n->display_order,
            ])
            ->all();

        $lines = [];
        foreach ($ingredientModels as $ingredient) {
            $grams = $this->gramsConverter->convert(
                $ingredient->food_source_record_id,
                $ingredient->measure_unit_id,
                (float) $ingredient->amount,
            );

            $label = $ingredient->ingredientRecord?->food?->name_ar ?? "سجل #{$ingredient->food_source_record_id}";

            if ($grams === null) {
                // لا يُستبعد المكوّن صمتاً: يُحتسب بوزن صفر فيظهر بوضوح في تحذير عدم تطابق
                // الوزن، بدل أن يختفي من الحساب دون تفسير.
                $grams = 0.0;
            }

            $nutrientValues = [];
            foreach ($valuesByRecord->get($ingredient->food_source_record_id, collect()) as $value) {
                $nutrientValues[$value->nutrient_id] = [
                    'amount' => (float) $value->amount_per_100g,
                    'unit' => $value->unit,
                ];
            }

            $lines[] = [
                'label' => $label,
                'grams' => $grams,
                'is_added_after_cooking' => (bool) $ingredient->is_added_after_cooking,
                'nutrients' => $nutrientValues,
            ];
        }

        return $this->calculator->calculate(
            ingredients: $lines,
            nutrientDefs: $nutrientDefs,
            weightBeforeCookingG: $recipe->weight_before_cooking_g !== null ? (float) $recipe->weight_before_cooking_g : null,
            weightAfterCookingG: $recipe->weight_after_cooking_g !== null ? (float) $recipe->weight_after_cooking_g : null,
            servings: (int) $recipe->servings,
        );
    }

    /**
     * Writes the "per 100g as served" view as the product's official nutrition — same table,
     * same shape as any food's values, just owned by product_id instead of
     * food_source_record_id. Replaces whatever was stored before (a product has exactly one
     * current analysis, never a history of competing ones — see the design discussion).
     */
    public function approve(Product $product, RecipeAnalysisResult $result): void
    {
        if (! $result->canSaveOfficialValue()) {
            throw new RuntimeException('لا يمكن اعتماد التحليل بلا إدخال الوزن بعد الطبخ.');
        }

        $dataSource = DataSource::where('code', 'internal_product_recipes')->first();
        if (! $dataSource) {
            throw new RuntimeException("data source 'internal_product_recipes' is not seeded — see data_sources_new_row.sql.");
        }

        $rows = collect($result->per100gAfterCooking())
            ->filter(fn (array $row) => $row['amount'] !== null)
            ->map(fn (array $row) => [
                'product_id' => $product->id,
                'nutrient_id' => $row['nutrient']['id'],
                'amount_per_100g' => $row['amount'],
                'unit' => $row['nutrient']['unit'],
                'method' => NutrientValueMethod::Calculated->value,
                'confidence_level' => ConfidenceLevel::Calculated->value,
                'notes' => $row['missing_from']
                    ? 'ناقص من: '.implode('، ', array_unique($row['missing_from']))
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (! $rows) {
            throw new RuntimeException('لا توجد قيم قابلة للاعتماد (كل النتائج فارغة).');
        }

        DB::transaction(function () use ($product, $rows) {
            FoodNutrientValue::where('product_id', $product->id)->delete();
            FoodNutrientValue::insert($rows);
        });
    }
}
