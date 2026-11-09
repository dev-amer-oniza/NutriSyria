<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use App\Filament\Clusters\Brands\Resources\Brands\RelationManagers\CategoriesRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRecipeIngredient;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** A restaurant's own menu sections (the owner's decisions 1a 2a 3a 4a). */
class ProductCategoriesTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    private Brand $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
        $this->other = Brand::create(['name' => 'مطعم آخر']);
    }

    private function category(Brand $brand, string $name, int $order = 0): ProductCategory
    {
        $category = new ProductCategory(['name' => $name, 'sort_order' => $order]);
        $category->brand_id = $brand->id;
        $category->save();

        return $category;
    }

    private function owner(): void
    {
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->brand->id);
    }

    public function test_an_owner_adds_sections_to_their_own_brand_in_order(): void
    {
        $this->owner();

        $page = fn () => Livewire::test(CategoriesRelationManager::class, ['ownerRecord' => $this->brand, 'pageClass' => EditBrand::class]);
        $page()->callAction(TestAction::make(CreateAction::class)->table(), ['name' => 'مقبلات'])->assertHasNoActionErrors();
        $page()->callAction(TestAction::make(CreateAction::class)->table(), ['name' => 'حلويات'])->assertHasNoActionErrors();
        $page()->callAction(TestAction::make(CreateAction::class)->table(), ['name' => 'مقبلات'])->assertHasActionErrors(['name' => 'unique']);

        $this->assertSame(['مقبلات', 'حلويات'], $this->brand->categories()->pluck('name')->all());
        $this->assertSame([1, 2], $this->brand->categories()->pluck('sort_order')->all());

        // The same name is fine in another restaurant: each brand has its own sections.
        $this->category($this->other, 'مقبلات');
        $this->assertSame(2, ProductCategory::where('name', 'مقبلات')->count());
    }

    public function test_an_owner_cannot_touch_another_brands_sections(): void
    {
        $this->owner();
        $theirs = $this->category($this->other, 'مشاوي');

        $this->assertFalse(auth()->user()->can('update', $theirs));
        $this->assertFalse(auth()->user()->can('delete', $theirs));
        $this->assertTrue(auth()->user()->can('update', $this->category($this->brand, 'مقبلات')));
    }

    public function test_a_product_takes_a_section_of_its_own_brand_only(): void
    {
        $this->owner();
        $mine = $this->category($this->brand, 'أطباق رئيسية');
        $theirs = $this->category($this->other, 'مشاوي');
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => 'مجدرة']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => $theirs->id])
            ->call('save')
            ->assertHasFormErrors(['category_id']);
        $this->assertNull($product->fresh()->category_id);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_id' => $mine->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($mine->id, $product->fresh()->category_id);
    }

    public function test_a_section_can_be_added_from_the_product_form(): void
    {
        $this->owner();
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => 'كنافة']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->mountFormComponentAction('category_id', 'createOption')
            ->setFormComponentActionData(['name' => 'حلويات'])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame($this->brand->id, ProductCategory::where('name', 'حلويات')->value('brand_id'));
    }

    public function test_deleting_a_section_keeps_its_products(): void
    {
        $this->owner();
        $section = $this->category($this->brand, 'سلطات');
        $product = Product::create(['brand_id' => $this->brand->id, 'name' => 'تبولة', 'category_id' => $section->id]);

        Livewire::test(CategoriesRelationManager::class, ['ownerRecord' => $this->brand, 'pageClass' => EditBrand::class])
            ->callAction(TestAction::make(DeleteAction::class)->table($section));

        $this->assertModelMissing($section);
        $this->assertNull($product->fresh()->category_id);
    }

    public function test_the_restaurant_page_shows_the_menu_in_its_sections(): void
    {
        $mains = $this->category($this->brand, 'أطباق رئيسية', 2);
        $starters = $this->category($this->brand, 'مقبلات', 1);
        $this->category($this->brand, 'حلويات', 3); // empty: not shown
        $this->approvedMeal('مجدرة', ['category_id' => $mains->id]);
        $this->approvedMeal('حمص', ['category_id' => $starters->id]);
        $this->approvedMeal('فول');
        Product::create(['brand_id' => $this->brand->id, 'name' => 'كبة لبنية', 'category_id' => $mains->id]);

        $this->get(route('site.brands.show', $this->brand))->assertOk()
            ->assertSeeInOrder(['href="#cat-'.$starters->id.'"', 'href="#cat-'.$mains->id.'"', 'href="#cat-other"'], false)
            ->assertSeeInOrder(['مقبلات', 'حمص', 'أطباق رئيسية', 'مجدرة', 'أطباق أخرى', 'فول', 'قيمها قيد المراجعة', 'كبة لبنية'])
            ->assertDontSee('id="cat-'.$this->brand->categories()->where('name', 'حلويات')->value('id').'"', false)
            ->assertSee('ns-pending__cat', false);

        // A rename shows at once: the public cache follows the sections too.
        $starters->update(['name' => 'مازة']);
        $this->get(route('site.brands.show', $this->brand))->assertSee('مازة');
    }

    public function test_a_restaurant_without_sections_keeps_one_plain_list(): void
    {
        $this->approvedMeal('مجدرة');

        $this->get(route('site.brands.show', $this->brand))->assertOk()
            ->assertSee('مجدرة')
            ->assertDontSee('أقسام المنيو')
            ->assertDontSee('أطباق أخرى');
    }

    public function test_the_recipe_lines_table_loads_with_several_lines(): void
    {
        $this->owner();
        $product = $this->approvedMeal('مجدرة', [], [$this->lentils, $this->ingredient('برغل')]);

        Livewire::test(RecipeIngredientsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertOk()
            ->assertCanSeeTableRecords(ProductRecipeIngredient::all());
    }
}
