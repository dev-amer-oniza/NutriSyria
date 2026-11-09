<?php

namespace Tests\Feature;

use App\Auth\Access;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Products\Pages\ListProducts;
use App\Filament\Clusters\Kitchen\Pages\KitchenRecipeAnalysis;
use App\Filament\Clusters\Kitchen\Resources\Categories\KitchenCategoryResource;
use App\Filament\Clusters\Kitchen\Resources\Categories\Pages\ManageKitchenCategories;
use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;
use App\Filament\Clusters\Kitchen\Resources\Dishes\Pages\CreateKitchenDish;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Site\RestaurantDirectory;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** "المطبخ السوري": the platform's own dishes, the admin's alone, with no review (the owner's decisions). */
class SyrianKitchenTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    private Brand $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
        $this->kitchen = Brand::platform();
    }

    /** An approved kitchen dish (the fixtures' meal, on the platform's brand). */
    private function kitchenDish(string $name, array $attributes = []): Product
    {
        $restaurant = $this->brand;
        $this->brand = $this->kitchen;
        $dish = $this->approvedMeal($name, $attributes);
        $this->brand = $restaurant;

        return $dish;
    }

    public function test_the_admin_adds_sections_and_dishes_to_the_kitchen(): void
    {
        $this->signInAs();

        Livewire::test(ManageKitchenCategories::class)
            ->callAction(TestAction::make(CreateAction::class), ['name' => 'طبخات'])
            ->assertHasNoActionErrors();
        $section = ProductCategory::where('name', 'طبخات')->firstOrFail();
        $this->assertSame($this->kitchen->id, $section->brand_id);

        Livewire::test(CreateKitchenDish::class)
            ->fillForm(['name' => 'مجدرة', 'category_id' => $section->id, 'recipe' => ['servings' => 4]])
            ->call('create')
            ->assertHasNoFormErrors();
        $dish = Product::where('name', 'مجدرة')->firstOrFail();
        $this->assertSame($this->kitchen->id, $dish->brand_id);
        $this->assertSame($section->id, $dish->category_id);

        $this->get(KitchenDishResource::getUrl('edit', ['record' => $dish]))->assertOk()->assertSee('تحليل الوجبة');
        $this->get(KitchenRecipeAnalysis::getUrl().'?product='.$dish->id)->assertOk()->assertDontSee('ملاحظات المراجعة');
    }

    public function test_the_admin_approves_a_dish_alone_and_no_review_is_asked(): void
    {
        $this->signInAs();
        $dish = Product::create(['brand_id' => $this->kitchen->id, 'name' => 'فتوش']);

        $this->assertTrue(auth()->user()->can('approveNutrition', $dish));
        $this->assertFalse(auth()->user()->can('sendReviewNote', $dish));
    }

    public function test_the_kitchen_pages_never_reach_a_restaurants_product(): void
    {
        $this->signInAs();
        $theirs = Product::create(['brand_id' => $this->brand->id, 'name' => 'شاورما']);

        $this->get(KitchenDishResource::getUrl('edit', ['record' => $theirs]))->assertNotFound();
        $this->get(KitchenRecipeAnalysis::getUrl().'?product='.$theirs->id)->assertNotFound();
    }

    public function test_a_brand_owner_does_not_see_the_kitchen(): void
    {
        $dish = Product::create(['brand_id' => $this->kitchen->id, 'name' => 'فتوش']);
        $this->signInAs(Access::BRAND_OWNER)->brands()->attach($this->brand->id);

        $this->get(KitchenDishResource::getUrl())->assertForbidden();
        $this->assertFalse(auth()->user()->can('view', $dish));
    }

    public function test_the_nutritionist_does_not_see_the_kitchen(): void
    {
        $dish = Product::create(['brand_id' => $this->kitchen->id, 'name' => 'فتوش']);
        $nutritionist = $this->signInAs(Access::NUTRITIONIST);

        $this->get(KitchenCategoryResource::getUrl())->assertForbidden();
        $this->assertFalse($nutritionist->can('view', $dish));
        $this->assertFalse($nutritionist->can('approveNutrition', $dish));
        Livewire::test(ListProducts::class)->assertCanNotSeeTableRecords([$dish]);
    }

    public function test_the_kitchen_is_never_listed_as_a_restaurant(): void
    {
        $this->signInAs();

        $this->assertNotContains($this->kitchen->id, BrandResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($this->kitchen->id, array_map(fn ($r) => $r->id, app(RestaurantDirectory::class)->all()));
        $this->get(route('site.brands.show', $this->kitchen->id))->assertNotFound();
    }

    public function test_the_site_shows_the_kitchen_in_its_sections(): void
    {
        $section = new ProductCategory(['name' => 'طبخات', 'sort_order' => 1]);
        $section->brand_id = $this->kitchen->id;
        $section->save();
        $dish = $this->kitchenDish('مجدرة', ['category_id' => $section->id]);
        $this->approvedMeal('شاورما');

        $this->get(route('site.kitchen'))->assertOk()
            ->assertSee('المطبخ السوري')->assertSee('طبخات')->assertSee('مجدرة')->assertDontSee('شاورما');

        // In the meals list, with the kitchen's name and a link to its page.
        $this->get(route('site.meals'))->assertOk()
            ->assertSee('مجدرة')->assertSee('شاورما')->assertSee(route('site.kitchen'), false);

        // "محسوب" without the nutritionist's review.
        $this->get(route('site.meals.show', $dish))->assertOk()
            ->assertSee('محسوبة من مكوّنات موثّقة المصدر وفق كمياتها في الوصفة.')
            ->assertDontSee('وراجعها أخصائي تغذية');
    }
}
