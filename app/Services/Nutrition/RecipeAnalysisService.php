<?php

namespace App\Services\Nutrition;

use App\Enums\ConfidenceLevel;
use App\Enums\FoodSourceStatus;
use App\Enums\NutrientValueMethod;
use App\Models\DataSource;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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
        private readonly NutrientGapFiller $gapFiller = new NutrientGapFiller(),
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
        $donorsByRecord = $this->donorsFor($ingredientModels->pluck('ingredientRecord')->filter()->unique('id'));

        $valuesByRecord = FoodNutrientValue::query()
            ->whereIn('food_source_record_id', $sourceRecordIds->merge($donorsByRecord->flatten(1)->pluck('id'))->unique())
            ->whereNotNull('amount_per_100g')
            ->get()
            ->groupBy('food_source_record_id');
        $valuesOf = fn (int $recordId) => $valuesByRecord->get($recordId, collect())
            ->mapWithKeys(fn (FoodNutrientValue $v) => [$v->nutrient_id => ['amount' => (float) $v->amount_per_100g, 'unit' => $v->unit]])
            ->all();

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
            $conversion = $this->gramsConverter->resolve(
                $ingredient->food_source_record_id,
                $ingredient->measure_unit_id,
                (float) $ingredient->amount,
                $ingredient->food_portion_id !== null ? (int) $ingredient->food_portion_id : null,
            );

            $label = $ingredient->ingredientRecord?->food?->name_ar ?? "سجل #{$ingredient->food_source_record_id}";

            // The chosen record's own values; what it does not report is filled from the same
            // food and form in the other approved sources (see NutrientGapFiller).
            $nutrientValues = $this->gapFiller->fill(
                $valuesOf($ingredient->food_source_record_id),
                $donorsByRecord->get($ingredient->food_source_record_id, collect())
                    ->map(fn (array $d) => ['source' => $d['source'], 'values' => $valuesOf($d['id'])])
                    ->all(),
                collect($nutrientDefs)->pluck('id', 'code')->all(),
            );

            $lines[] = [
                'label' => $label,
                // null = not convertible: the calculator reports the line and blocks approval
                // instead of counting it as zero grams.
                'grams' => $conversion->grams,
                'problem' => $conversion->message,
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
     * For each chosen record: the OTHER approved records of the same food in the same form
     * (both form-less counts as the same form), best data source first. Never another form:
     * boiled chickpeas do not borrow from raw ones.
     *
     * @param  SupportCollection<int, FoodSourceRecord>  $records
     * @return SupportCollection<int, SupportCollection<int, array{id: int, source: string}>>
     */
    private function donorsFor(SupportCollection $records): SupportCollection
    {
        if ($records->isEmpty()) {
            return collect();
        }

        $candidates = FoodSourceRecord::query()
            ->join('data_sources', 'data_sources.id', '=', 'food_source_records.data_source_id')
            ->whereIn('food_source_records.food_id', $records->pluck('food_id')->unique())
            ->where('food_source_records.status', FoodSourceStatus::Active->value)
            ->where('data_sources.is_active', true)
            ->orderBy('data_sources.priority')
            ->orderBy('food_source_records.id')
            ->get(['food_source_records.id', 'food_source_records.food_id', 'food_source_records.food_form_id', 'data_sources.name as source_name']);

        return $records->mapWithKeys(fn (FoodSourceRecord $r) => [$r->id => $candidates
            ->filter(fn ($c) => (int) $c->id !== (int) $r->id
                && (int) $c->food_id === (int) $r->food_id
                && self::formKey($c->food_form_id) === self::formKey($r->food_form_id))
            ->map(fn ($c) => ['id' => (int) $c->id, 'source' => (string) $c->source_name])
            ->values()]);
    }

    /** Where the stored number's parts came from, for anyone reading the product's values later. */
    private static function provenanceNote(array $row): ?string
    {
        $parts = [];
        if ($row['missing_from']) {
            $parts[] = 'ناقص من: '.implode('، ', array_unique($row['missing_from']));
        }
        if ($row['borrowed'] ?? []) {
            $parts[] = 'مستعار من مصدر آخر لنفس الغذاء والشكل: '.implode('، ', array_map(
                fn (string $label, string $source) => "{$label} ({$source})",
                array_keys($row['borrowed']), $row['borrowed'],
            ));
        }
        if ($row['computed'] ?? []) {
            $parts[] = 'سعرات محسوبة من البروتين والدهون والكربوهيدرات (4/9/4): '.implode('، ', $row['computed']);
        }

        return $parts ? implode(' | ', $parts) : null;
    }

    private static function formKey(mixed $formId): ?int
    {
        return $formId === null ? null : (int) $formId;
    }

    /**
     * Writes the "per 100g as served" view as the product's official nutrition — same table,
     * same shape as any food's values, just owned by product_id instead of
     * food_source_record_id. Replaces whatever was stored before (a product has exactly one
     * current analysis, never a history of competing ones — see the design discussion).
     */
    public function approve(Product $product, RecipeAnalysisResult $result, ?User $approvedBy = null): void
    {
        if ($result->unconvertedIngredients) {
            throw new RuntimeException('لا يمكن اعتماد التحليل: مكوّنات لم يمكن تحويلها إلى غرام: '
                .implode('، ', array_unique(array_column($result->unconvertedIngredients, 'label'))).'.');
        }
        if (! $result->canSaveOfficialValue()) {
            throw new RuntimeException('لا يمكن اعتماد التحليل بلا إدخال الوزن بعد الطبخ.');
        }
        if ($product->reviewNotes()->open()->exists()) {
            throw new RuntimeException('على هذا المنتج ملاحظة مفتوحة: ينتظر تعديل صاحب البراند، أو أغلق الملاحظة أولاً.');
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
                'notes' => self::provenanceNote($row),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if (! $rows) {
            throw new RuntimeException('لا توجد قيم قابلة للاعتماد (كل النتائج فارغة).');
        }

        DB::transaction(function () use ($product, $rows, $approvedBy) {
            FoodNutrientValue::where('product_id', $product->id)->delete();
            FoodNutrientValue::insert($rows);
            // Who made these the official values, and when.
            // Set here only, never through a form (not in $fillable).
            $product->recipe?->forceFill([
                'nutrition_approved_by' => $approvedBy?->getKey(),
                'nutrition_approved_at' => now(),
            ])->save();
        });
    }
}
