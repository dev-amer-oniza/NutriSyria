<?php

namespace App\Services\Site\Kitchen;

use App\Enums\FoodSourceStatus;
use App\Models\Brand;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use Illuminate\Support\Facades\DB;

/**
 * Adds the dishes of a kitchen recipe file to "المطبخ السوري" (the owner's decisions 1a–4a):
 *  - a dish already there by name is left as it is, so running the file again adds only what
 *    is new and never undoes the owner's own changes;
 *  - every ingredient must be an approved record (the highest priority source for its food and
 *    form), or the whole dish is set aside with the reason: never half a recipe;
 *  - nothing is approved here: the owner reviews each analysis and approves it in the panel.
 * The records are read in one query, whatever the number of dishes.
 */
class KitchenRecipeImporter
{
    /**
     * @param  list<array{name: string, category: string, description: ?string, servings: int, weight_after_g: float, with_bread: bool, lines: list<array{food: string, form: string, grams: float, after: bool}>}>  $dishes
     * @return list<array{dish: string, result: 'added'|'exists'|'skipped', reason: ?string}>
     */
    public function import(array $dishes): array
    {
        $kitchen = Brand::platform();
        $existing = array_flip($kitchen->products()->pluck('name')->all());
        $records = $this->records(array_merge(...array_map(fn ($d) => $d['lines'], $dishes ?: [['lines' => []]])));
        $gram = MeasureUnit::query()->where('code', 'g')->firstOrFail();
        $categories = $kitchen->categories()->pluck('id', 'name')->all(); // name => id

        $report = [];
        foreach ($dishes as $dish) {
            if (isset($existing[$dish['name']])) {
                $report[] = ['dish' => $dish['name'], 'result' => 'exists', 'reason' => null];

                continue;
            }
            $missing = array_values(array_unique(array_map(
                fn ($l) => "{$l['food']} ({$l['form']})",
                array_filter($dish['lines'], fn ($l) => ! isset($records["{$l['food']}|{$l['form']}"])),
            )));
            if ($missing || ! $dish['lines']) {
                $report[] = ['dish' => $dish['name'], 'result' => 'skipped', 'reason' => $missing
                    ? 'لا يوجد سجل معتمد لـ: '.implode('، ', $missing)
                    : 'بلا مكوّنات.'];

                continue;
            }

            DB::transaction(function () use ($kitchen, $dish, $records, $gram, &$categories) {
                if (! isset($categories[$dish['category']])) {
                    $new = new ProductCategory(['name' => $dish['category'], 'sort_order' => count($categories) + 1]);
                    $new->brand_id = $kitchen->id;
                    $new->save();
                    $categories[$dish['category']] = $new->id;
                }
                $product = new Product(['name' => $dish['name'], 'description' => $dish['description'], 'is_active' => true, 'eaten_with_bread' => $dish['with_bread']]);
                $product->brand_id = $kitchen->id;
                $product->category_id = $categories[$dish['category']];
                $product->save();

                $recipe = ProductRecipe::create([
                    'product_id' => $product->id,
                    'servings' => $dish['servings'],
                    'weight_before_cooking_g' => array_sum(array_map(fn ($l) => $l['after'] ? 0 : $l['grams'], $dish['lines'])),
                    'weight_after_cooking_g' => $dish['weight_after_g'],
                ]);
                foreach ($dish['lines'] as $i => $line) {
                    $record = $records["{$line['food']}|{$line['form']}"];
                    ProductRecipeIngredient::create([
                        'product_recipe_id' => $recipe->id,
                        'food_source_record_id' => $record->id,
                        'food_form_id' => $record->food_form_id,
                        'measure_unit_id' => $gram->id,
                        'amount' => $line['grams'],
                        'is_added_after_cooking' => $line['after'],
                        'sort_order' => $i,
                    ]);
                }
            });
            $existing[$dish['name']] = true;
            $report[] = ['dish' => $dish['name'], 'result' => 'added', 'reason' => null];
        }

        return $report;
    }

    /**
     * The approved record for each food and form, the highest priority source first.
     *
     * @param  list<array{food: string, form: string, grams: float, after: bool}>  $lines
     * @return array<string, FoodSourceRecord> keyed "name|form"
     */
    private function records(array $lines): array
    {
        if (! $lines) {
            return [];
        }

        return FoodSourceRecord::query()
            ->select('food_source_records.*', 'foods.name_ar as food_name', 'food_forms.code as form_code')
            ->join('foods', 'foods.id', '=', 'food_source_records.food_id')
            ->join('food_forms', 'food_forms.id', '=', 'food_source_records.food_form_id')
            ->join('data_sources', 'data_sources.id', '=', 'food_source_records.data_source_id')
            ->where('food_source_records.status', FoodSourceStatus::Active)
            ->where('data_sources.is_active', true)
            ->whereIn('foods.name_ar', array_unique(array_column($lines, 'food')))
            ->orderBy('data_sources.priority')
            ->get()
            ->reduce(function (array $carry, FoodSourceRecord $r) {
                $carry[$r->getAttribute('food_name').'|'.$r->getAttribute('form_code')] ??= $r;

                return $carry;
            }, []);
    }
}
