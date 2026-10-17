<?php

namespace Tests\Feature;

use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
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
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RecipePortionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_size_field_appears_and_is_saved(): void
    {
        // Users and permissions live on the fixed "central" connection: point it at the test database.
        config(['database.connections.central' => config('database.connections.sqlite')]);
        DB::purge('central');
        DB::connection('central')->setPdo(DB::connection()->getPdo());
        $this->actingAs(User::forceCreate(['name' => 'admin', 'email' => 'admin@example.test', 'password' => 'x']));
        Filament::setCurrentPanel('admin');

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
        $product = Product::create(['brand_id' => Brand::create(['name' => 'مطعم'])->id, 'name' => 'بصل مشوي']);
        ProductRecipe::create(['product_id' => $product->id, 'servings' => 1]);

        $component = Livewire::test(RecipeIngredientsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->fillForm(['food_source_record_id' => $onion->id, 'measure_unit_id' => $gram->id])
            ->assertFormFieldHidden('food_portion_id')
            ->fillForm(['measure_unit_id' => $piece->id])
            ->assertFormFieldVisible('food_portion_id')
            ->fillForm(['amount' => 2])
            ->callMountedAction()
            ->assertHasFormErrors(['food_portion_id' => 'required']);

        $component->fillForm(['food_portion_id' => $sizes[150]->id])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame($sizes[150]->id, ProductRecipeIngredient::sole()->food_portion_id);

        // A default size is preselected, and the field is then optional.
        $sizes[70]->update(['is_default' => true]);
        Livewire::test(RecipeIngredientsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->fillForm(['food_source_record_id' => $onion->id, 'measure_unit_id' => $piece->id])
            ->assertSchemaStateSet(['food_portion_id' => $sizes[70]->id])
            ->assertSee('(كبيرة)');
    }
}
