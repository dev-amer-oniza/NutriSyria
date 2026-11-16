<?php

namespace Tests\Concerns;

use App\Data\Nutrition\RecipeDraft;
use App\Data\Nutrition\RecipeLine;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Nutrition\RecipeAnalysisService;
use Illuminate\Support\Facades\DB;

/** A kitchen dish (lentils cooked, oil on top) with its approved analysis, for "اطبخها على طريقتك". */
trait KitchenRecipeFixtures
{
    protected array $n = [];

    protected DataSource $sr;

    protected MeasureUnit $g;

    protected FoodSourceRecord $lentils;

    protected FoodSourceRecord $oil;

    protected Product $dish;

    protected function setUpKitchenRecipe(): void
    {
        foreach ([['energy_kcal', 'kcal'], ['protein_g', 'g'], ['fat_g', 'g'], ['carbohydrate_g', 'g']] as $i => [$code, $unit]) {
            $this->n[$code] = Nutrient::create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'unit' => $unit, 'group' => 'x', 'display_order' => $i]);
        }
        $this->sr = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc', 'priority' => 100]);
        DataSource::create(['code' => 'internal_product_recipes', 'name' => 'recipes', 'type' => 'recipe', 'priority' => 0]);
        $this->g = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);

        $this->lentils = $this->record('عدس', ['energy_kcal' => 116, 'protein_g' => 9, 'fat_g' => 0.4, 'carbohydrate_g' => 20]);
        $this->oil = $this->record('زيت زيتون', ['energy_kcal' => 884, 'protein_g' => 0, 'fat_g' => 100, 'carbohydrate_g' => 0]);

        // Ours: 280 g cooked, 20 g of oil on top, 240 g served in 2 servings.
        $this->dish = $this->kitchenDish([[$this->lentils, 280, false], [$this->oil, 20, true]], servings: 2, after: 240);
    }

    protected function record(string $name, array $values, string $status = 'active'): FoodSourceRecord
    {
        $record = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => $name])->id, 'data_source_id' => $this->sr->id,
            'external_ref' => uniqid(), 'status' => $status,
        ]);
        foreach ($values as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $record->id, 'nutrient_id' => $this->n[$code]->id,
                'amount_per_100g' => $amount, 'unit' => $this->n[$code]->unit]);
        }

        return $record;
    }

    /** An approved dish of the platform's kitchen (or of $brand). */
    protected function kitchenDish(array $lines, int $servings, float $after, ?Brand $brand = null): Product
    {
        $product = Product::create(['brand_id' => ($brand ?? Brand::platform())->id, 'name' => 'مجدرة', 'is_active' => true]);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => $servings, 'weight_after_cooking_g' => $after]);
        foreach ($lines as $i => [$record, $grams, $afterCooking]) {
            ProductRecipeIngredient::create(['product_recipe_id' => $recipe->id, 'food_source_record_id' => $record->id,
                'measure_unit_id' => $this->g->id, 'amount' => $grams, 'is_added_after_cooking' => $afterCooking, 'sort_order' => $i]);
        }
        $service = app(RecipeAnalysisService::class);
        $service->approve($product, $service->analyze($product));
        DB::table('product_recipe_ingredients')->where('product_recipe_id', $recipe->id)->update(['updated_at' => now()->subDay()]);
        DB::table('product_recipes')->where('id', $recipe->id)->update(['updated_at' => now()->subDay()]);

        return $product;
    }

    /** @param  list<array{0: FoodSourceRecord, 1: float, 2: bool}>  $lines */
    protected function draft(array $lines, int $servings = 2, ?float $after = null): RecipeDraft
    {
        return new RecipeDraft(
            lines: array_map(fn (array $l) => new RecipeLine($l[0]->id, $this->g->id, $l[1], addedAfterCooking: $l[2]), $lines),
            servings: $servings,
            weightAfterCookingG: $after,
        );
    }
}
