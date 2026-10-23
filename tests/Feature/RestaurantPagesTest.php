<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** The restaurants pages, the home sections, the shared cache and the N+1 guard. */
class RestaurantPagesTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    private function person(): User
    {
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'moderate', 'goal' => 'lose', 'health_conditions' => [], 'allergies' => [], 'completed_at' => now(),
        ]);

        return $user;
    }

    /** Queries that read the catalog's own tables during one request. */
    private function catalogQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();

        return collect(DB::getQueryLog())
            ->filter(fn (array $q) => preg_match('/from "(brands|products|product_recipes|food_nutrient_values|product_recipe_ingredients|foods)"/', $q['query']))
            ->count();
    }

    public function test_the_list_shows_active_restaurants_with_search_and_a_delivery_filter(): void
    {
        $this->brand->update(['has_delivery' => true, 'city' => 'damascus']);
        $this->approvedMeal('مجدرة');
        Brand::create(['name' => 'مطعم الشام']);
        Brand::create(['name' => 'مطعم موقوف', 'is_active' => false]);

        $this->get(route('site.brands'))->assertOk()
            ->assertSeeInOrder(['بيت المجدرة', 'مطعم الشام'])          // most approved meals first
            ->assertSee('دمشق')
            ->assertDontSee('مطعم موقوف');

        $this->get(route('site.brands', ['q' => 'الشآم']))->assertSee('مطعم الشام')->assertDontSee('بيت المجدرة');
        $this->get(route('site.brands', ['delivery' => 1]))->assertSee('بيت المجدرة')->assertDontSee('مطعم الشام');
    }

    public function test_a_restaurant_page_shows_its_details_and_whole_menu(): void
    {
        $this->brand->update([
            'description' => 'أكلات بيتية منذ 1990.', 'city' => 'aleppo', 'address' => 'حلب، العزيزية',
            'opening_hours' => 'يومياً 10 ص – 11 م', 'phone_1' => '021 123 4567', 'whatsapp' => '0912 345 678',
            'instagram_url' => 'https://instagram.com/bait', 'has_delivery' => true, 'delivery_note' => 'توصيل لكل حلب',
        ]);
        $this->approvedMeal('مجدرة');
        Product::create(['brand_id' => $this->brand->id, 'name' => 'فتة جديدة', 'description' => 'لم تُعتمد بعد']);
        Product::create(['brand_id' => $this->brand->id, 'name' => 'منتج موقوف', 'is_active' => false]);

        $this->get(route('site.brands.show', $this->brand))->assertOk()
            ->assertSee('أكلات بيتية منذ 1990.')
            ->assertSee('حلب')
            ->assertSee('حلب، العزيزية')
            ->assertSee('يومياً 10 ص – 11 م')
            ->assertSee('توصيل لكل حلب')
            ->assertSee('href="https://wa.me/963912345678"', false)
            ->assertSee('href="tel:', false)
            ->assertSee('https://instagram.com/bait')
            ->assertSee('مجدرة')
            ->assertSee(route('site.meals.show', Product::where('name', 'مجدرة')->value('id')), false)
            ->assertSee('قيمها قيد المراجعة')
            ->assertSee('فتة جديدة')
            ->assertDontSee('منتج موقوف');
    }

    public function test_a_delivery_note_shows_only_when_the_restaurant_delivers(): void
    {
        $this->brand->update(['has_delivery' => false, 'delivery_note' => 'توصيل لكل حلب']);

        $this->get(route('site.brands.show', $this->brand))->assertOk()->assertDontSee('توصيل لكل حلب');
    }

    public function test_a_hidden_restaurant_is_not_found(): void
    {
        $this->brand->update(['is_active' => false]);

        $this->get(route('site.brands.show', $this->brand))->assertNotFound();
        $this->get(route('site.brands.show', 999))->assertNotFound();
    }

    public function test_the_home_page_shows_restaurants_and_meals_that_suit_you(): void
    {
        $this->approvedMeal('مجدرة');
        $this->approvedMeal('منسف كبير', per100g: ['energy_kcal' => 500, 'protein_g' => 12, 'carbohydrate_g' => 30, 'fat_g' => 25]);

        $this->get('/')->assertOk()
            ->assertSee('مطاعم معنا')
            ->assertSee('بيت المجدرة')
            ->assertSee('وجبات تناسبك')
            ->assertSee('أنشئ حسابك وأخبرنا عن جسمك');

        $this->actingAs($this->person());
        $html = $this->get('/')->assertOk()->getContent();
        $suits = substr($html, strpos($html, 'id="suits-title"'), strpos($html, 'id="dishes-title"') - strpos($html, 'id="suits-title"'));

        $this->assertStringContainsString('مجدرة', $suits);
        $this->assertStringNotContainsString('منسف كبير', $suits); // 1250 kcal: not for him now
    }

    public function test_the_catalog_is_cached_and_cleared_by_any_change(): void
    {
        $meal = $this->approvedMeal('مجدرة');
        $this->assertGreaterThan(0, $this->catalogQueries(fn () => $this->get(route('site.brands'))->assertOk()), 'first visit builds the cache');

        $this->assertSame(0, $this->catalogQueries(fn () => $this->get(route('site.brands'))->assertOk()), 'served from the cache');
        $this->assertSame(0, $this->catalogQueries(fn () => $this->get(route('site.meals'))->assertOk()));

        $this->brand->update(['name' => 'بيت المجدرة الجديد']);
        $this->get(route('site.brands'))->assertSee('بيت المجدرة الجديد');

        $meal->update(['name' => 'مجدرة بالبرغل']);
        $this->get(route('site.meals'))->assertSee('مجدرة بالبرغل');
    }

    public function test_building_the_lists_takes_the_same_queries_for_many_restaurants(): void
    {
        $this->approvedMeal('مجدرة');
        $few = $this->catalogQueries(fn () => $this->get(route('site.brands'))->assertOk());

        foreach (range(1, 5) as $i) {
            $brand = Brand::create(['name' => "مطعم {$i}"]);
            $this->approvedMeal("وجبة {$i}")->update(['brand_id' => $brand->id]);
        }
        $many = $this->catalogQueries(fn () => $this->get(route('site.brands'))->assertOk());

        $this->assertGreaterThan(0, $few, 'a cold cache reads the tables');
        $this->assertSame($few, $many, 'no query per restaurant (N+1)');
    }

    public function test_the_details_are_entered_in_the_panel_and_links_must_be_web_links(): void
    {
        $this->signInAs(\App\Auth\Access::SUPER_ADMIN);

        Livewire::test(EditBrand::class, ['record' => $this->brand->id])
            ->fillForm(['instagram_url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['instagram_url']);

        Livewire::test(EditBrand::class, ['record' => $this->brand->id])
            ->fillForm(['city' => 'homs', 'address' => 'حمص، الحمرا', 'opening_hours' => 'يومياً', 'whatsapp' => '0933 111 222',
                'instagram_url' => 'https://instagram.com/bait', 'has_delivery' => true, 'delivery_note' => 'توصيل لكل حمص'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('حمص، الحمرا', $this->brand->fresh()->address);
        $this->assertSame('حمص', $this->brand->fresh()->city->label());
    }
}
