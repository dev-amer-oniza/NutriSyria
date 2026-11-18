<?php

namespace Tests\Feature;

use App\Enums\NutritionApprovalStatus;
use App\Models\Brand;
use App\Models\DataSource;
use App\Models\FoodNutrientValue;
use App\Models\Product;
use App\Models\User;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Nutrition\RecipeAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** Step 4: "من أين؟", what each ingredient gives a meal, and "ليش؟" under the verdict. */
class MealSourcesTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    /** The whole recipe's fat (500 g cooked, 2 servings: a serving is half). */
    private const FAT = ['fat_g' => [
        ['label' => 'طحينة', 'amount' => 28.0, 'after' => false],
        ['label' => 'زيت زيتون', 'amount' => 14.0, 'after' => true],
        ['label' => 'حمص', 'amount' => 6.4, 'after' => false],
    ]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpMeals();
    }

    private function meal(bool $kitchen, array $per100g = [], array $contributions = self::FAT): Product
    {
        $product = $this->approvedMeal('مسبّحة', per100g: $per100g + ['energy_kcal' => 166, 'protein_g' => 6, 'carbohydrate_g' => 15, 'fat_g' => 9.7, 'fiber_g' => 4]);
        if ($kitchen) {
            DB::table('products')->where('id', $product->id)->update(['brand_id' => Brand::platform()->id]);
        }
        DB::table('product_recipes')->where('product_id', $product->id)->update(['contributions' => json_encode($contributions)]);

        return $product;
    }

    private function person(): User
    {
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity_level' => 'light', 'goal' => 'lose', 'allergies' => [], 'completed_at' => now(),
        ]);

        return $user;
    }

    public function test_a_kitchen_dish_shows_the_grams_each_ingredient_gives(): void
    {
        $this->get(route('site.meals.show', $this->meal(kitchen: true)))->assertOk()
            ->assertSee('من أين الدهون؟')
            ->assertSeeInOrder(['طحينة', '14', '58%', 'زيت زيتون على الوجه', '7', '29%', 'حمص', '3.2', '13%'])
            ->assertDontSee('كميات وصفة المطعم سرّية');
    }

    public function test_a_restaurant_dish_shows_the_order_only(): void
    {
        $this->get(route('site.meals.show', $this->meal(kitchen: false)))->assertOk()
            ->assertSee('من أين الدهون؟')
            ->assertSeeInOrder(['طحينة', 'زيت زيتون على الوجه', 'حمص'])
            ->assertSee('كميات وصفة المطعم سرّية')
            ->assertDontSee('58%')
            ->assertDontSee('ns-src__amount', false);
    }

    public function test_the_ingredients_behind_a_reason_sit_under_it(): void
    {
        // 20 g fat per 100 g: 50 g a serving, far over this person's meal share.
        $meal = $this->meal(kitchen: true, per100g: ['fat_g' => 20]);
        $this->actingAs($this->person());

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSeeInOrder(['مدى مناسبتها لك', 'الدهون فيها', 'من أين الدهون؟', 'طحينة']);
    }

    public function test_what_would_help_is_tried_and_offered_with_a_way_to_cook_it(): void
    {
        // The nutritionist's 15 g of fat a meal: leaving the oil off helps.
        $meal = $this->meal(kitchen: true);
        $user = $this->person();
        app(PersonalCriteria::class)->setByNutritionist($user, 'meal_fat_g', 15, User::forceCreate(['name' => 'د. ريم', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true]));
        $this->actingAs($user);

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('ماذا تستطيع أن تفعل')
            ->assertSee('بلا زيت زيتون على الوجه')
            ->assertSee('اطبخها هكذا')
            ->assertSee(e(route('site.kitchen.recipe', $meal->id).'?'), false);
    }

    public function test_a_restaurant_dish_is_offered_only_what_can_be_asked_for_without_amounts(): void
    {
        // The nutritionist's 15 g of fat a meal; 17 g fat a serving, 7 g of it from the oil on top: without it, 10 g, within 15 g.
        $meal = $this->meal(kitchen: false, per100g: ['fat_g' => 6.8], contributions: ['fat_g' => [
            ['label' => 'طحينة', 'amount' => 16.0, 'after' => false],
            ['label' => 'زيت زيتون', 'amount' => 14.0, 'after' => true],
            ['label' => 'حمص', 'amount' => 4.0, 'after' => false],
        ]]);
        $user = $this->person();
        app(PersonalCriteria::class)->setByNutritionist($user, 'meal_fat_g', 15, User::forceCreate(['name' => 'د. ريم', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true]));
        $this->actingAs($user);

        $this->get(route('site.meals.show', $meal))->assertOk()
            ->assertSee('ماذا تستطيع أن تفعل')
            ->assertSee('اطلبها بلا زيت زيتون على الوجه')
            ->assertSee('ما يمكنك طلبه من المطعم')
            ->assertDontSee('اطبخها هكذا')
            ->assertDontSee('الكمية')
            ->assertDontSee('والدهون فيها');
    }

    public function test_approving_keeps_what_each_ingredient_gives(): void
    {
        DataSource::create(['code' => 'internal_product_recipes', 'name' => 'وصفات المنتجات', 'type' => 'recipe']);
        $product = $this->mealWithValues();
        $analysis = app(RecipeAnalysisService::class);

        $analysis->approve($product, $analysis->analyze($product));

        $this->assertSame('عدس', $product->recipe->fresh()->contributions['fat_g'][0]['label']);
    }

    public function test_the_meals_approved_before_are_filled_without_reading_as_changed(): void
    {
        $product = $this->mealWithValues();
        $this->assertNull($product->recipe->contributions);

        $this->artisan('meals:contributions')->expectsOutputToContain('Filled 1 meals.')->assertSuccessful();

        $recipe = $product->recipe->fresh();
        $this->assertEqualsWithDelta(300 / 100 * 1.5, $recipe->contributions['fat_g'][0]['amount'], 0.001);
        $loaded = NutritionApprovalStatus::withApprovalData(Product::query())->find($product->id);
        $this->assertSame(NutritionApprovalStatus::Approved, NutritionApprovalStatus::of($loaded), 'still approved');
    }

    /** An approved meal of 300 g lentils whose record holds 1.5 g fat per 100 g. */
    private function mealWithValues(): Product
    {
        FoodNutrientValue::create([
            'food_source_record_id' => $this->lentils->id, 'nutrient_id' => $this->nutrients['fat_g']->id,
            'amount_per_100g' => 1.5, 'unit' => 'g',
        ]);

        return $this->approvedMeal('مجدرة');
    }
}
