<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\CreatePreparation;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\EditPreparation;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\ListPreparations;
use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Preparation;
use App\Models\PreparationIngredient;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\MealCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** "تحضيراتي": a brand's own preparations, used as one ingredient (the owner's decisions 1a 2a 3a 4a). */
class PreparationsTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    private FoodSourceRecord $dates;

    private FoodSourceRecord $water;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
        // Made-up test values per 100 g.
        $this->dates = $this->ingredient('تمر', allergens: []);
        $this->water = $this->ingredient('ماء', allergens: []);
        foreach ([[$this->dates, 280, 2, 0.2, 75], [$this->water, 0, 0, 0, 0], [$this->lentils, 116, 9, 0.4, 20]] as [$record, $kcal, $protein, $fat, $carbs]) {
            foreach (['energy_kcal' => $kcal, 'protein_g' => $protein, 'fat_g' => $fat, 'carbohydrate_g' => $carbs] as $code => $amount) {
                FoodNutrientValue::create(['food_source_record_id' => $record->id, 'nutrient_id' => $this->nutrients[$code]->id,
                    'amount_per_100g' => $amount, 'unit' => $this->nutrients[$code]->unit]);
            }
        }
    }

    /** كريمة تمر: 300 g dates + 100 g water. */
    private function cream(?float $weightAfter = null, ?Brand $brand = null): Preparation
    {
        $prep = Preparation::create(['brand_id' => ($brand ?? $this->brand)->id, 'name' => 'كريمة تمر', 'weight_after_g' => $weightAfter]);
        foreach ([[$this->dates, 300], [$this->water, 100]] as $i => [$record, $grams]) {
            PreparationIngredient::create(['preparation_id' => $prep->id, 'food_source_record_id' => $record->id,
                'measure_unit_id' => $this->grams->id, 'amount' => $grams, 'sort_order' => $i]);
        }

        return $prep;
    }

    /** @param  list<array{0: ?FoodSourceRecord, 1: float, 2?: ?Preparation, 3?: ?MeasureUnit}>  $lines */
    private function product(array $lines, int $servings = 1): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => 'كرالا']);
        $recipe = ProductRecipe::create(['product_id' => $product->id, 'servings' => $servings]);
        foreach ($lines as $i => $line) {
            ProductRecipeIngredient::create([
                'product_recipe_id' => $recipe->id, 'food_source_record_id' => $line[0]?->id, 'preparation_id' => ($line[2] ?? null)?->id,
                'measure_unit_id' => ($line[3] ?? $this->grams)->id, 'amount' => $line[1], 'sort_order' => $i,
            ]);
        }

        return $product;
    }

    private function kcal(Product $product): ?float
    {
        $total = collect(app(RecipeAnalysisService::class)->analyze($product)->totals)->firstWhere('nutrient.code', 'energy_kcal');

        return $total['amount'];
    }

    public function test_a_preparation_counts_as_its_foods_scaled_to_the_grams_used(): void
    {
        // 200 g of a 400 g cream = 150 g dates + 50 g water = 420 kcal, as if written by hand.
        $byHand = $this->product([[$this->dates, 150], [$this->water, 50]]);
        $withCream = $this->product([[null, 200, $this->cream()]]);

        $this->assertEqualsWithDelta(420, $this->kcal($byHand), 0.001);
        $this->assertEqualsWithDelta(420, $this->kcal($withCream), 0.001);

        // Under its own name: "من أين السعرات؟ كريمة تمر".
        $result = app(RecipeAnalysisService::class)->analyze($withCream);
        $this->assertSame(['كريمة تمر'], array_column($result->lineGrams, 'label'));
        $this->assertEqualsWithDelta(200, $result->gramsTotal, 0.001);
    }

    public function test_a_cream_that_lost_weight_is_richer_per_gram(): void
    {
        // Cooked down to 200 g: 200 g of it is the whole 300 g of dates (840 kcal).
        $product = $this->product([[null, 200, $this->cream(weightAfter: 200)]]);
        $this->assertEqualsWithDelta(840, $this->kcal($product), 0.001);
    }

    public function test_a_preparation_is_weighed_in_grams_or_kilos_only(): void
    {
        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household']);
        $kilo = MeasureUnit::create(['code' => 'kg', 'name_ar' => 'كيلو', 'dimension' => 'mass', 'base_factor' => 1000]);
        $cream = $this->cream();

        $this->assertEqualsWithDelta(420 * 5, $this->kcal($this->product([[null, 1, $cream, $kilo]])), 0.001);

        $result = app(RecipeAnalysisService::class)->analyze($this->product([[null, 1, $cream, $cup]]));
        $this->assertSame('كريمة تمر', $result->unconvertedIngredients[0]['label']);
        $this->assertFalse($result->canSaveOfficialValue());
    }

    public function test_the_owner_writes_a_preparation_and_picks_it_in_a_product(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->brand->id);
        $this->get(PreparationResource::getUrl('index'))->assertOk();

        Livewire::test(CreatePreparation::class)
            ->fillForm(['name' => 'كريمة تمر', 'ingredients' => [
                ['food_source_record_id' => $this->dates->id, 'amount' => 300, 'measure_unit_id' => $this->grams->id],
                ['food_source_record_id' => $this->water->id, 'amount' => 100, 'measure_unit_id' => $this->grams->id],
            ]])
            ->assertSee('كل 100 غ')
            ->call('create')
            ->assertHasNoFormErrors();
        $cream = Preparation::with('ingredients')->sole();
        $this->assertSame($this->brand->id, $cream->brand_id);
        $this->assertCount(2, $cream->ingredients);

        $product = $this->product([[$this->lentils, 100]]);
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.recipe.ingredients', ['row' => ['food_source_record_id' => 'p'.$cream->id, 'amount' => 200, 'measure_unit_id' => $this->grams->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $line = ProductRecipeIngredient::query()->where('product_recipe_id', $product->recipe->id)->sole();
        $this->assertSame($cream->id, $line->preparation_id);
        $this->assertNull($line->food_source_record_id);

        // Opened again, the row shows the preparation.
        $state = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->get('data.recipe.ingredients');
        $this->assertSame(['p'.$cream->id], array_values(array_column($state, 'food_source_record_id')));
    }

    public function test_another_brands_preparation_is_refused(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->brand->id);
        $theirs = $this->cream(brand: Brand::create(['name' => 'غيرنا']));
        $product = $this->product([[$this->lentils, 100]]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set('data.recipe.ingredients', ['row' => ['food_source_record_id' => 'p'.$theirs->id, 'amount' => 200, 'measure_unit_id' => $this->grams->id]])
            ->call('save')
            ->assertHasErrors();
        $this->assertSame(0, ProductRecipeIngredient::query()->whereNotNull('preparation_id')->count());
    }

    public function test_a_preparation_in_use_is_not_deleted(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->brand->id);
        $cream = $this->cream();
        $this->product([[null, 200, $cream]]);

        Livewire::test(EditPreparation::class, ['record' => $cream->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('ما فيك تحذفه هلق');
        $this->assertModelExists($cream);
        $this->assertStringContainsString('كرالا', $cream->deletionBlocker());

        ProductRecipeIngredient::query()->delete();
        Livewire::test(ListPreparations::class)->callTableAction('delete', $cream);
        $this->assertModelMissing($cream);
    }

    public function test_changing_a_preparation_sends_its_products_back_to_the_nutritionist(): void
    {
        $cream = $this->cream();
        $product = $this->approvedMeal('كرالا', ingredient: $this->lentils);
        ProductRecipeIngredient::create(['product_recipe_id' => $product->recipe->id, 'preparation_id' => $cream->id,
            'measure_unit_id' => $this->grams->id, 'amount' => 100]);
        DB::table('product_recipe_ingredients')->update(['updated_at' => '2026-09-30']);
        $this->assertSame(NutritionApprovalStatus::Approved, NutritionApprovalStatus::of(NutritionApprovalStatus::withApprovalData(Product::query())->find($product->id)));

        $cream->ingredients()->first()->update(['amount' => 350]);

        $this->assertSame(NutritionApprovalStatus::Changed, NutritionApprovalStatus::of(NutritionApprovalStatus::withApprovalData(Product::query())->find($product->id)));
    }

    public function test_the_site_names_the_preparation_and_counts_its_allergens(): void
    {
        $milk = $this->ingredient('حليب', allergens: ['milk']);
        $cream = $this->cream();
        PreparationIngredient::create(['preparation_id' => $cream->id, 'food_source_record_id' => $milk->id,
            'measure_unit_id' => $this->grams->id, 'amount' => 20]);
        $product = $this->approvedMeal('كرالا', ingredient: $this->lentils);
        ProductRecipeIngredient::create(['product_recipe_id' => $product->recipe->id, 'preparation_id' => $cream->id,
            'measure_unit_id' => $this->grams->id, 'amount' => 100]);
        DB::table('product_recipe_ingredients')->update(['updated_at' => '2026-09-30']);

        $meal = app(MealCatalog::class)->find($product->id);
        $this->assertEqualsCanonicalizing(['عدس', 'كريمة تمر'], $meal->ingredients);
        $this->assertContains('milk', $meal->allergens);
    }

    public function test_the_admin_has_no_preparations_page(): void
    {
        $this->signInAs(Access::SUPER_ADMIN);
        $this->get(PreparationResource::getUrl('index'))->assertNotFound();
    }
}
