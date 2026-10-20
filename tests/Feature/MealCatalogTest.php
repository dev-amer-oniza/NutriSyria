<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MealCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Brand $brand;

    private FoodSourceRecord $lentils;

    private MeasureUnit $grams;

    /** @var array<string, Nutrient> */
    private array $nutrients = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = Brand::create(['name' => 'بيت المجدرة']);
        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $this->lentils = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'عدس'])->id, 'data_source_id' => $source->id,
            'external_ref' => '172420', 'status' => FoodSourceStatus::Active, 'notes' => 'FDC: 172420',
        ]);
        $this->grams = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);

        foreach (['energy_kcal' => 'kcal', 'protein_g' => 'g', 'carbohydrate_g' => 'g', 'fat_g' => 'g', 'fiber_g' => 'g'] as $code => $unit) {
            $this->nutrients[$code] = Nutrient::create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'unit' => $unit, 'group' => 'proximate', 'is_core' => true]);
        }
    }

    /** An approved product: 500 g cooked, 2 servings, so one serving is 250 g. */
    private function approvedMeal(string $name, array $attributes = [], ?FoodSourceRecord $ingredient = null, string $approvedAt = '2026-10-01'): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => $name] + $attributes);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 2, 'weight_after_cooking_g' => 500]);
        ProductRecipeIngredient::create([
            'product_recipe_id' => $recipe->id, 'food_source_record_id' => ($ingredient ?? $this->lentils)->id,
            'measure_unit_id' => $this->grams->id, 'amount' => 300, 'sort_order' => 1,
        ]);

        foreach (['energy_kcal' => 166, 'protein_g' => 6, 'carbohydrate_g' => 24.8, 'fat_g' => 4.8, 'fiber_g' => 3] as $code => $per100g) {
            FoodNutrientValue::create([
                'product_id' => $product->id, 'nutrient_id' => $this->nutrients[$code]->id,
                'amount_per_100g' => $per100g, 'unit' => $this->nutrients[$code]->unit,
                'method' => NutrientValueMethod::Calculated,
            ]);
        }

        // The recipe was last edited the day before its approval.
        $editedAt = date('Y-m-d', strtotime($approvedAt.' -1 day'));
        DB::table('product_recipe_ingredients')->where('product_recipe_id', $recipe->id)->update(['updated_at' => $editedAt]);
        DB::table('product_recipes')->where('id', $recipe->id)->update(['updated_at' => $editedAt, 'nutrition_approved_at' => $approvedAt]);

        return $product;
    }

    public function test_an_approved_meal_shows_its_values_per_serving(): void
    {
        $this->approvedMeal('مجدرة', ['description' => 'عدس وبرغل']);

        $card = app(MealCatalog::class)->latest()[0];

        $this->assertSame('مجدرة', $card['name']);
        $this->assertSame('بيت المجدرة', $card['brand']);
        $this->assertSame(250, $card['portion']);
        $this->assertSame(415, $card['kcal']);      // 166 × 2.5
        $this->assertSame(15, $card['protein']);
        $this->assertSame(62, $card['carbs']);
        $this->assertSame(12, $card['fat']);
        $this->assertSame('calculated', $card['verification']);
        $this->assertNull($card['image']);
    }

    public function test_only_approved_public_meals_are_listed_newest_first(): void
    {
        $this->approvedMeal('أقدم', approvedAt: '2026-09-01');
        $this->approvedMeal('أحدث', approvedAt: '2026-10-05');
        $this->approvedMeal('منتج موقوف', ['is_active' => false]);
        Product::create(['brand_id' => $this->brand->id, 'name' => 'بلا اعتماد']);

        $changed = $this->approvedMeal('تغيّرت وصفته');
        DB::table('product_recipe_ingredients')
            ->whereIn('product_recipe_id', ProductRecipe::where('product_id', $changed->id)->pluck('id'))
            ->update(['updated_at' => '2026-10-10']);

        $hiddenBrand = Brand::create(['name' => 'براند موقوف', 'is_active' => false]);
        $this->approvedMeal('من براند موقوف')->update(['brand_id' => $hiddenBrand->id]);

        $this->assertSame(['أحدث', 'أقدم'], array_column(app(MealCatalog::class)->latest(), 'name'));
    }

    public function test_a_meal_using_a_proxy_food_is_estimated(): void
    {
        $labneh = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'لبنة'])->id, 'data_source_id' => $this->lentils->data_source_id,
            'external_ref' => '170903', 'status' => FoodSourceStatus::Active,
            'notes' => 'مقاربة: زبادي يوناني كامل الدسم بدل اللبنة | FDC: 170903',
        ]);
        $this->approvedMeal('سندويشة لبنة', ingredient: $labneh);

        $this->assertSame('estimated', app(MealCatalog::class)->latest()[0]['verification']);
    }

    public function test_home_page_lists_real_meals_or_says_they_are_coming(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertSee('قريباً تظهر هنا وجبات المطاعم');

        $this->approvedMeal('مجدرة بيت المجدرة');

        $this->withoutVite()->get('/')
            ->assertOk()
            ->assertSee('مجدرة بيت المجدرة')
            ->assertDontSee('قريباً تظهر هنا وجبات المطاعم');
    }
}
