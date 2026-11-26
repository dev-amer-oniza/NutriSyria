<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodForm;
use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Models\ProductRecipeIngredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipePortionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_size_field_appears_and_is_saved(): void
    {
        // Recipes are the restaurant's side of the panel: the admin does not see them.
        $brand = Brand::create(['name' => 'مطعم']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($brand->id);

        $source = DataSource::create(['code' => 'usda_fdc_sr_legacy', 'name' => 'USDA SR Legacy', 'type' => 'usda_fdc']);
        $onion = FoodSourceRecord::create([
            'food_id' => Food::create(['name_ar' => 'بصل'])->id, 'food_form_id' => FoodForm::create(['code' => 'raw', 'name_ar' => 'نيء'])->id,
            'data_source_id' => $source->id, 'external_ref' => '170000', 'status' => 'active',
        ]);
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'dimension' => 'count']);
        $gram = MeasureUnit::create(['code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1]);
        $sizes = [];
        foreach (['small' => 70, 'large (3" dia)' => 150] as $description => $grams) {
            $sizes[$grams] = FoodPortion::create([
                'food_id' => $onion->food_id, 'measure_unit_id' => $piece->id, 'amount' => 1, 'gram_weight' => $grams,
                'food_source_record_id' => $onion->id, 'description' => $description,
            ]);
        }
        $product = Product::create(['brand_id' => $brand->id, 'name' => 'بصل مشوي']);
        ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);

        $row = fn (array $values) => ['data.recipe.ingredients' => ['row' => $values + ['food_source_record_id' => $onion->id, 'amount' => 2]]];
        $field = 'recipe.ingredients.row.food_portion_id';

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set($row(['measure_unit_id' => $gram->id]))
            ->assertFormFieldHidden($field)
            ->set($row(['measure_unit_id' => $piece->id]))
            ->assertFormFieldVisible($field)
            ->call('save')
            ->assertHasFormErrors([$field => 'required']);

        $page->set('data.recipe.ingredients.row.food_portion_id', $sizes[150]->id)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($sizes[150]->id, ProductRecipeIngredient::sole()->food_portion_id);

        // A default size settles it: the field is then optional, and the row still saves.
        ProductRecipeIngredient::query()->delete();
        $sizes[70]->update(['is_default' => true]);
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->set($row(['measure_unit_id' => $piece->id]))
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(1, ProductRecipeIngredient::count());
    }
}
