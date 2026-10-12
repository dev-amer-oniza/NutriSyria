<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Nutrition\RecipeAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A recipe line uses the chosen record and fills only what it lacks from the same food and form. */
class NutrientBorrowingTest extends TestCase
{
    use RefreshDatabase;

    private array $n = [];

    private array $src = [];

    private FoodForm $asSold;

    private Food $butter;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['energy_kcal', 'kcal'], ['protein_g', 'g'], ['fat_g', 'g'], ['carbohydrate_g', 'g'], ['fat_sat_g', 'g']] as $i => [$code, $unit]) {
            $this->n[$code] = Nutrient::create(['code' => $code, 'name_ar' => $code, 'name_en' => $code, 'unit' => $unit, 'group' => 'x', 'display_order' => $i]);
        }
        foreach ([['usda_fdc_foundation', 'USDA Foundation Foods', 90], ['usda_fdc_sr_legacy', 'USDA SR Legacy', 100], ['national', 'Syrian table', 50], ['internal_product_recipes', 'recipes', 0]] as [$code, $name, $priority]) {
            $this->src[$code] = DataSource::create(['code' => $code, 'name' => $name, 'type' => 'x', 'priority' => $priority]);
        }
        $this->asSold = FoodForm::create(['code' => 'as_sold', 'name_ar' => 'كما يباع']);
        $this->butter = Food::create(['name_ar' => 'زبدة']);
    }

    private function record(string $source, array $values, ?FoodForm $form = null, string $status = 'active'): FoodSourceRecord
    {
        $r = FoodSourceRecord::create([
            'food_id' => $this->butter->id, 'food_form_id' => ($form ?? $this->asSold)->id,
            'data_source_id' => $this->src[$source]->id, 'external_ref' => uniqid(), 'status' => $status,
        ]);
        foreach ($values as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $r->id, 'nutrient_id' => $this->n[$code]->id,
                'amount_per_100g' => $amount, 'unit' => $this->n[$code]->unit]);
        }

        return $r;
    }

    /** 100 g of the given record, 100 g after cooking. */
    private function analyse(FoodSourceRecord $record): array
    {
        $g = MeasureUnit::firstOrCreate(['code' => 'g'], ['name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);
        $product = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'منتج']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => 1, 'weight_after_cooking_g' => 100]);
        ProductRecipeIngredient::create(['product_recipe_id' => $recipe->id, 'food_source_record_id' => $record->id,
            'measure_unit_id' => $g->id, 'amount' => 100]);

        $service = new RecipeAnalysisService();
        $result = $service->analyze($product);
        $service->approve($product, $result);

        return [$result, $product];
    }

    private function total($result, string $code): ?float
    {
        return $result->totals[$this->n[$code]->id]['amount'];
    }

    public function test_foundation_butter_borrows_energy_and_saturated_fat_from_sr_legacy(): void
    {
        $foundation = $this->record('usda_fdc_foundation', ['fat_g' => 82.2]);
        $this->record('usda_fdc_sr_legacy', ['energy_kcal' => 717, 'protein_g' => 0.85, 'fat_g' => 81.1, 'carbohydrate_g' => 0.06, 'fat_sat_g' => 51.4]);

        [$r, $product] = $this->analyse($foundation);

        $this->assertEqualsWithDelta(82.2, $this->total($r, 'fat_g'), 1e-6);        // own value kept
        $this->assertEqualsWithDelta(717, $this->total($r, 'energy_kcal'), 1e-6);   // borrowed
        $this->assertEqualsWithDelta(51.4, $this->total($r, 'fat_sat_g'), 1e-6);
        $this->assertSame(['زبدة' => 'USDA SR Legacy'], $r->totals[$this->n['energy_kcal']->id]['borrowed']);
        $this->assertSame(4, $r->filledValueCount());

        $note = FoodNutrientValue::where('product_id', $product->id)->where('nutrient_id', $this->n['energy_kcal']->id)->value('notes');
        $this->assertStringContainsString('مستعار', $note);
        $this->assertStringContainsString('USDA SR Legacy', $note);
    }

    public function test_energy_is_computed_from_own_macronutrients_before_borrowing(): void
    {
        $foundation = $this->record('usda_fdc_foundation', ['protein_g' => 1, 'fat_g' => 80, 'carbohydrate_g' => 1]);
        $this->record('usda_fdc_sr_legacy', ['energy_kcal' => 717]);

        [$r] = $this->analyse($foundation);

        $this->assertEqualsWithDelta(4 + 720 + 4, $this->total($r, 'energy_kcal'), 1e-6);
        $this->assertSame(['زبدة'], $r->totals[$this->n['energy_kcal']->id]['computed']);
    }

    public function test_the_highest_priority_donor_wins(): void
    {
        $foundation = $this->record('usda_fdc_foundation', ['fat_g' => 82]);
        $this->record('usda_fdc_sr_legacy', ['fat_sat_g' => 51]);
        $this->record('national', ['fat_sat_g' => 55]);   // priority 50 beats SR Legacy's 100

        [$r] = $this->analyse($foundation);

        $this->assertEqualsWithDelta(55, $this->total($r, 'fat_sat_g'), 1e-6);
    }

    public function test_nothing_is_borrowed_from_another_form_or_an_unapproved_record(): void
    {
        $foundation = $this->record('usda_fdc_foundation', ['fat_g' => 82]);
        $this->record('usda_fdc_sr_legacy', ['fat_sat_g' => 51], FoodForm::create(['code' => 'melted', 'name_ar' => 'مذابة']));
        $this->record('national', ['fat_sat_g' => 55], status: 'pending_review');
        $this->record('usda_fdc_sr_legacy', ['fat_sat_g' => 60], status: 'rejected');

        [$r] = $this->analyse($foundation);

        $this->assertNull($this->total($r, 'fat_sat_g'));
        $this->assertSame(0, $r->filledValueCount());
    }
}
