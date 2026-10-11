<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodNutrientValue;
use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\GramsConverter;
use App\Services\Nutrition\RecipeAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GramsConverterTest extends TestCase
{
    use RefreshDatabase;

    private array $units = [];

    private FoodForm $raw;

    private FoodForm $boiled;

    private DataSource $usda;

    private FoodSourceRecord $rawChickpeas;

    private FoodSourceRecord $boiledChickpeas;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['g', 'غرام', 'mass', 1], ['oz', 'أونصة', 'mass', 28.349523], ['ml', 'مل', 'volume', 1],
            ['fl_oz', 'أونصة سائلة', 'volume', 29.57353], ['cup', 'كوب', 'household', null], ['tbsp', 'ملعقة كبيرة', 'household', null],
        ] as [$code, $nameAr, $dimension, $factor]) {
            $this->units[$code] = MeasureUnit::create([
                'code' => $code, 'name_ar' => $nameAr, 'name_en' => $code, 'dimension' => $dimension, 'base_factor' => $factor,
            ]);
        }
        MeasureUnitAlias::create(['measure_unit_id' => $this->units['cup']->id, 'label' => 'cup']);

        $this->raw = FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء']);
        $this->boiled = FoodForm::create(['code' => 'boiled', 'name_ar' => 'مسلوق']);
        $this->usda = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);

        $chickpeas = Food::create(['name_ar' => 'حمص']);
        $this->rawChickpeas = $this->record($chickpeas, $this->raw, '173756');
        $this->boiledChickpeas = $this->record($chickpeas, $this->boiled, '173757');

        // Same order as a real import: the raw record's cup comes first.
        $this->portion($this->rawChickpeas, 'cup', 200);
        $this->portion($this->boiledChickpeas, 'cup', 164);
    }

    private function record(Food $food, ?FoodForm $form, string $ref, string $status = 'active'): FoodSourceRecord
    {
        return FoodSourceRecord::create([
            'food_id' => $food->id, 'food_form_id' => $form?->id, 'data_source_id' => $this->usda->id,
            'external_ref' => $ref, 'status' => $status,
        ]);
    }

    private function portion(FoodSourceRecord $record, string $unit, float $grams, array $over = []): FoodPortion
    {
        return FoodPortion::create($over + [
            'food_id' => $record->food_id, 'measure_unit_id' => $this->units[$unit]->id, 'amount' => 1,
            'gram_weight' => $grams, 'food_source_record_id' => $record->id, 'food_form_id' => $record->food_form_id,
            'basis' => 'usda', 'confidence_level' => 'reference',
        ]);
    }

    private function converter(): GramsConverter
    {
        return new GramsConverter();
    }

    public function test_boiled_and_raw_chickpeas_each_use_their_own_cup_weight(): void
    {
        $this->assertEqualsWithDelta(164.0, $this->converter()->convert($this->boiledChickpeas->id, $this->units['cup']->id, 1), 1e-6);
        $this->assertEqualsWithDelta(200.0, $this->converter()->convert($this->rawChickpeas->id, $this->units['cup']->id, 1), 1e-6);
    }

    public function test_ounces_convert_without_any_portion(): void
    {
        $this->assertEqualsWithDelta(56.699046, $this->converter()->convert($this->boiledChickpeas->id, $this->units['oz']->id, 2), 1e-6);
    }

    public function test_a_portion_entered_on_the_record_page_inherits_the_record_form(): void
    {
        $lentils = Food::create(['name_ar' => 'عدس']);
        $rawLentils = $this->record($lentils, $this->raw, '172420');
        $boiledLentils = $this->record($lentils, $this->boiled, '172421');
        // PortionsRelationManager saves food_id + food_source_record_id, but no food_form_id.
        $this->portion($rawLentils, 'tbsp', 12, ['food_form_id' => null]);

        $c = $this->converter()->resolve($boiledLentils->id, $this->units['tbsp']->id, 1);

        $this->assertSame(GramsConversion::OTHER_FORM_ONLY, $c->status);
        $this->assertEqualsWithDelta(12.0, $this->converter()->convert($rawLentils->id, $this->units['tbsp']->id, 1), 1e-6);
    }

    public function test_alias_labels_make_a_described_portion_plain(): void
    {
        $this->portion($this->boiledChickpeas, 'cup', 246, ['description' => 'cup, mashed']);
        $this->portion($this->boiledChickpeas, 'cup', 164, ['description' => '1 cup']);

        $this->assertEqualsWithDelta(164.0, $this->converter()->convert($this->boiledChickpeas->id, $this->units['cup']->id, 1), 1e-6);
    }

    public function test_expired_portions_are_ignored(): void
    {
        FoodPortion::query()->update(['valid_to' => now()->subDay()]);

        $this->assertSame(GramsConversion::NO_PORTION, $this->converter()->resolve($this->boiledChickpeas->id, $this->units['cup']->id, 1)->status);
    }

    public function test_ml_is_converted_through_a_fluid_ounce_portion(): void
    {
        $lemon = Food::create(['name_ar' => 'عصير ليمون']);
        $juice = $this->record($lemon, $this->raw, '167747');
        $this->portion($juice, 'fl_oz', 30.5);

        $c = $this->converter()->resolve($juice->id, $this->units['ml']->id, 100);

        $this->assertSame(GramsConversion::METHOD_VOLUME_DENSITY, $c->method);
        $this->assertEqualsWithDelta(100 * 30.5 / 29.57353, $c->grams, 1e-4);
    }

    public function test_unknown_unit_and_record_fail_cleanly(): void
    {
        $this->assertSame(GramsConversion::UNKNOWN_UNIT, $this->converter()->resolve($this->boiledChickpeas->id, 999999, 1)->status);
        $this->assertSame(GramsConversion::UNKNOWN_RECORD, $this->converter()->resolve(999999, $this->units['cup']->id, 1)->status);
    }

    public function test_analysis_reports_an_unconvertible_ingredient_and_refuses_to_approve_it(): void
    {
        $energy = Nutrient::create(['code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'name_en' => 'Energy', 'unit' => 'kcal', 'group' => 'energy', 'is_core' => true]);
        DataSource::create(['code' => 'internal_product_recipes', 'name' => 'وصفات المنتجات', 'type' => 'internal_product_recipes']);

        $oil = $this->record(Food::create(['name_ar' => 'زيت زيتون']), null, '171413');
        $this->portion($oil, 'tbsp', 13.5);
        foreach ([[$this->boiledChickpeas, 164], [$oil, 884]] as [$record, $kcal]) {
            FoodNutrientValue::create(['food_source_record_id' => $record->id, 'nutrient_id' => $energy->id, 'amount_per_100g' => $kcal, 'unit' => 'kcal']);
        }

        $product = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'حمص بزيت']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 1, 'weight_after_cooking_g' => 350]);
        $line = fn (FoodSourceRecord $r, string $unit, float $amount, int $order) => ProductRecipeIngredient::create([
            'product_recipe_id' => $recipe->id, 'food_source_record_id' => $r->id,
            'measure_unit_id' => $this->units[$unit]->id, 'amount' => $amount, 'sort_order' => $order,
        ]);
        $line($this->boiledChickpeas, 'cup', 2, 1);   // 328 g
        $line($oil, 'tbsp', 1, 2);                    // 13.5 g
        $mlLine = $line($oil, 'ml', 30, 3);           // no volume portion for oil: not convertible

        $service = new RecipeAnalysisService();
        $result = $service->analyze($product);

        $this->assertSame(341.5, $result->gramsTotal);
        $this->assertCount(1, $result->unconvertedIngredients);
        $this->assertSame('زيت زيتون', $result->unconvertedIngredients[0]['label']);
        $this->assertStringContainsString('مل', $result->unconvertedIngredients[0]['problem']);
        $this->assertFalse($result->canSaveOfficialValue());

        try {
            $service->approve($product, $result);
            $this->fail('approve() must refuse an analysis with unconverted ingredients');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('زيت زيتون', $e->getMessage());
        }
        $this->assertSame(0, FoodNutrientValue::where('product_id', $product->id)->count());

        // Once the problem line is fixed, the same recipe analyses and approves.
        $mlLine->delete();
        $fixed = $service->analyze($product->fresh());
        $this->assertTrue($fixed->canSaveOfficialValue());
        $this->assertEqualsWithDelta(328 * 1.64 + 13.5 * 8.84, $fixed->totals[$energy->id]['amount'], 1e-6);
        $service->approve($product, $fixed);
        $this->assertSame(1, FoodNutrientValue::where('product_id', $product->id)->count());
    }
}
