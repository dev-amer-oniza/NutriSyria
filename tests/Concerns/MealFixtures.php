<?php

namespace Tests\Concerns;

use App\Enums\FoodSourceStatus;
use App\Enums\NutrientValueMethod;
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
use App\Services\Site\MealCatalog;
use Illuminate\Support\Facades\DB;

/** Approved restaurant meals for the site's tests. Call setUpMeals() in setUp(). */
trait MealFixtures
{
    protected Brand $brand;

    protected FoodSourceRecord $lentils;

    protected MeasureUnit $grams;

    /** @var array<string, Nutrient> */
    protected array $nutrients = [];

    /** Per 100 g of the cooked dish (a lentil and bulgur mujaddara). */
    protected const PER_100G = ['energy_kcal' => 166, 'protein_g' => 6, 'carbohydrate_g' => 24.8, 'fat_g' => 4.8, 'fiber_g' => 3];

    protected function setUpMeals(): void
    {
        $this->brand = Brand::create(['name' => 'بيت المجدرة']);
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $this->lentils = $this->ingredient('عدس', $source->id);
        $this->grams = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);

        $units = ['energy_kcal' => 'kcal', 'sodium_mg' => 'mg', 'iron_mg' => 'mg', 'calcium_mg' => 'mg'];
        foreach (MealCatalog::NUTRIENTS as $code) {
            $this->nutrients[$code] = Nutrient::create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'unit' => $units[$code] ?? 'g', 'group' => 'proximate', 'is_core' => true]);
        }
    }

    /** An approved ingredient record for a food, with its allergens (checked when given). */
    protected function ingredient(string $name, ?int $sourceId = null, ?array $allergens = null, string $notes = 'FDC: 1'): FoodSourceRecord
    {
        $food = Food::create(['name_ar' => $name, 'allergens' => $allergens ?? [], 'allergens_checked' => $allergens !== null]);

        return FoodSourceRecord::create([
            'food_id' => $food->id, 'data_source_id' => $sourceId ?? $this->lentils->data_source_id,
            'external_ref' => (string) $food->id, 'status' => FoodSourceStatus::Active, 'notes' => $notes,
        ]);
    }

    /**
     * An approved product: 500 g cooked, 2 servings, so one serving is 250 g.
     *
     * @param  list<FoodSourceRecord>|FoodSourceRecord|null  $ingredient
     * @param  array<string, float>|null  $per100g
     */
    protected function approvedMeal(string $name, array $attributes = [], FoodSourceRecord|array|null $ingredient = null, string $approvedAt = '2026-10-01', ?array $per100g = null): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => $name] + $attributes);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 2, 'weight_after_cooking_g' => 500]);

        foreach (is_array($ingredient) ? $ingredient : [$ingredient ?? $this->lentils] as $i => $record) {
            ProductRecipeIngredient::create([
                'product_recipe_id' => $recipe->id, 'food_source_record_id' => $record->id,
                'measure_unit_id' => $this->grams->id, 'amount' => 300, 'sort_order' => $i + 1,
            ]);
        }

        foreach ($per100g ?? self::PER_100G as $code => $amount) {
            FoodNutrientValue::create([
                'product_id' => $product->id, 'nutrient_id' => $this->nutrients[$code]->id,
                'amount_per_100g' => $amount, 'unit' => $this->nutrients[$code]->unit,
                'method' => NutrientValueMethod::Calculated,
            ]);
        }

        // The recipe was last edited the day before its approval.
        $editedAt = date('Y-m-d', strtotime($approvedAt.' -1 day'));
        DB::table('product_recipe_ingredients')->where('product_recipe_id', $recipe->id)->update(['updated_at' => $editedAt]);
        DB::table('product_recipes')->where('id', $recipe->id)->update(['updated_at' => $editedAt, 'nutrition_approved_at' => $approvedAt]);

        return $product;
    }
}
