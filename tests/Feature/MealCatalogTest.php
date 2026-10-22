<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductRecipe;
use App\Services\Site\MealCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

class MealCatalogTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMeals();
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
        $labneh = $this->ingredient('لبنة', notes: 'مقاربة: زبادي يوناني كامل الدسم بدل اللبنة | FDC: 170903');
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
