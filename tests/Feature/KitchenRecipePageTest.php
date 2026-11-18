<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\FoodPortion;
use App\Models\MeasureUnit;
use App\Models\User;
use App\Services\Nutrition\Criteria\PersonalCriteria;
use App\Services\Site\Kitchen\KitchenIngredientCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\KitchenRecipeFixtures;
use Tests\TestCase;

/** "اطبخها على طريقتك", phase 2: the site's page, its ingredient list and its analysis. */
class KitchenRecipePageTest extends TestCase
{
    use KitchenRecipeFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpKitchenRecipe();
    }

    private function body(array $lines, int $servings = 2, ?float $after = null): array
    {
        return [
            'lines' => array_map(fn (array $l) => ['record_id' => $l[0]->id, 'unit_id' => $this->g->id, 'amount' => $l[1], 'after' => $l[2] ?? false], $lines),
            'servings' => $servings,
            'weight_after' => $after,
        ];
    }

    public function test_a_kitchen_dish_opens_with_our_recipe_and_its_page_links_to_it(): void
    {
        $this->get(route('site.meals.show', $this->dish))->assertOk()->assertSee(route('site.kitchen.recipe', $this->dish->id), false);

        $recipe = $this->get(route('site.kitchen.recipe', $this->dish->id))->assertOk()
            ->assertSee('اطبخها على طريقتك: مجدرة')
            ->viewData('recipe');
        $this->assertSame([$this->lentils->id, $this->oil->id], array_column($recipe['lines'], 'record_id'));
        $this->assertSame([false, true], array_column($recipe['lines'], 'after'));
        $this->assertSame([2, 240.0], [$recipe['servings'], $recipe['weight_after']]);
    }

    public function test_a_suggestion_opens_the_recipe_with_the_change_applied(): void
    {
        // "اطبخها هكذا" (step 6): without the oil on top, the lentils at half.
        $query = ['c' => [['i' => 'زيت زيتون', 'a' => 1, 'k' => 0], ['i' => 'عدس', 'a' => 0, 'k' => 0.5]]];
        $response = $this->get(route('site.kitchen.recipe', $this->dish->id).'?'.http_build_query($query))->assertOk();

        $this->assertSame('بلا زيت زيتون على الوجه، وعدس بنصف الكمية', $response->viewData('applied'));
        $recipe = $response->viewData('recipe');
        $this->assertSame([$this->lentils->id], array_column($recipe['lines'], 'record_id'));
        $this->assertSame([140.0], array_column($recipe['lines'], 'amount'));
        $this->assertNull($recipe['weight_after'], 'the cooked weight is estimated again');
        // "ارجع لوصفتنا" goes back to ours.
        $this->assertCount(2, $response->viewData('original')['lines']);
    }

    public function test_their_own_result_offers_a_change_to_try_on_the_spot(): void
    {
        // 40 g oil on top for 2: 20 g fat a serving, past the nutritionist's 15 g a meal.
        $user = User::forceCreate(['name' => 'سامر', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true, 'email_verified_at' => now()]);
        $user->profile()->create([
            'sex' => 'male', 'birth_year' => now()->year - 30, 'height_cm' => 178, 'weight_kg' => 80, 'activity_level' => 'light',
            'goal' => 'maintain', 'allergies' => [], 'completed_at' => now(),
        ]);
        app(PersonalCriteria::class)->setByNutritionist($user, 'meal_fat_g', 15, User::forceCreate(['name' => 'د. ريم', 'email' => uniqid().'@example.com', 'password' => 'x-secret-1', 'is_active' => true]));

        $html = $this->actingAs($user)
            ->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$this->lentils, 280], [$this->oil, 40, true]], servings: 2, after: 260))
            ->assertOk()->json('html');

        $this->assertStringContainsString('ماذا تستطيع أن تفعل', $html);
        $this->assertStringContainsString('بلا زيت زيتون على الوجه', $html);
        $this->assertStringContainsString('جرّبها', $html);
        $this->assertStringNotContainsString('اطبخها هكذا', $html, 'on their own recipe, the change is applied in place');
    }

    public function test_a_change_that_is_not_one_we_offer_opens_our_recipe_as_it_is(): void
    {
        foreach ([['i' => 'عدس', 'a' => 0, 'k' => 0.3], ['i' => 'سكر', 'a' => 0, 'k' => 0], ['i' => 'عدس', 'a' => 1, 'k' => 0]] as $cut) {
            $response = $this->get(route('site.kitchen.recipe', $this->dish->id).'?'.http_build_query(['c' => [$cut]]))->assertOk();
            $this->assertNull($response->viewData('applied'));
            $this->assertCount(2, $response->viewData('recipe')['lines']);
        }
    }

    public function test_a_restaurant_dish_has_no_such_page(): void
    {
        $restaurant = $this->kitchenDish([[$this->lentils, 100, false]], servings: 1, after: 100, brand: Brand::create(['name' => 'مطعم']));

        $this->get(route('site.meals.show', $restaurant))->assertOk()->assertDontSee(route('site.kitchen.recipe', $restaurant->id), false);
        $this->get(route('site.kitchen.recipe', $restaurant->id))->assertNotFound();
        $this->postJson(route('site.kitchen.recipe.analyze', $restaurant->id), $this->body([[$this->lentils, 100]]))->assertStatus(422);
    }

    public function test_the_ingredient_list_holds_approved_records_with_their_units_and_costs_no_query_twice(): void
    {
        $this->record('سمنة', ['fat_g' => 99], status: 'pending_review');

        $list = $this->getJson(route('site.kitchen.ingredients'))->assertOk()->json();
        $this->assertSame(['زيت زيتون', 'عدس'], array_column($list, 'name'));
        $this->assertEquals([['id' => $this->g->id, 'name' => 'غرام', 'grams' => 1, 'choices' => [], 'default' => null]], $list[0]['units']);
        $this->assertStringContainsString('عدس', $list[1]['search']);

        DB::enableQueryLog();
        $this->getJson(route('site.kitchen.ingredients'))->assertOk();
        $catalogQueries = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'food_source_records'));
        $this->assertSame([], array_values($catalogQueries));
    }

    public function test_analysing_returns_their_values_beside_ours_and_stores_nothing(): void
    {
        $before = DB::table('food_nutrient_values')->count();

        $html = $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$this->lentils, 280], [$this->oil, 10, true]], servings: 3, after: 230))
            ->assertOk()->json('html');

        $this->assertStringContainsString('حسب وصفتك: هذا التحليل غير معتمد ولا يُحفظ.', $html);
        $this->assertStringContainsString('مقارنة بوصفتنا', $html);
        $this->assertStringContainsString('أنشئ حسابك', $html); // a guest is invited to sign up
        // "من أين؟" in grams for the visitor's own recipe (step 4, 3a): 10 g oil on top = 3.3 g fat a serving.
        $this->assertStringContainsString('من أين الدهون؟', $html);
        $this->assertStringContainsString('زيت زيتون على الوجه', $html);
        $this->assertSame($before, DB::table('food_nutrient_values')->count());
    }

    public function test_an_unweighed_dish_is_estimated_and_said_so(): void
    {
        $html = $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$this->lentils, 560], [$this->oil, 20, true]]))
            ->assertOk()->json('html');

        $this->assertStringContainsString('قدّرنا وزن طبختك بعد الطبخ', $html);
    }

    public function test_bad_recipes_get_plain_arabic_reasons(): void
    {
        $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), ['lines' => [], 'servings' => 2])
            ->assertStatus(422)->assertJsonPath('errors.lines.0', 'أضف مكوّناً واحداً على الأقل.');

        $pending = $this->record('سمنة', ['fat_g' => 99], status: 'pending_review');
        $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$pending, 20]]))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'غير متوفرة في قاعدتنا المعتمدة'));
    }

    public function test_analysing_is_limited_per_visitor(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$this->lentils, 100]]))->assertOk();
        }

        $this->postJson(route('site.kitchen.recipe.analyze', $this->dish->id), $this->body([[$this->lentils, 100]]))
            ->assertStatus(429)->assertJsonPath('message', 'حلّلت كثيراً خلال دقيقة. انتظر قليلاً ثم حاول.');
    }

    public function test_each_ingredient_offers_its_own_units_and_sizes(): void
    {
        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household']);
        $piece = MeasureUnit::create(['code' => 'piece', 'name_ar' => 'حبة', 'dimension' => 'count']);
        $onion = $this->record('بصل', ['energy_kcal' => 40]);
        $portion = fn (MeasureUnit $u, float $grams, ?string $size = null) => FoodPortion::create([
            'food_id' => $onion->food_id, 'food_source_record_id' => $onion->id, 'measure_unit_id' => $u->id,
            'amount' => 1, 'gram_weight' => $grams, 'description' => $size,
        ]);
        $portion($cup, 160);
        $small = $portion($piece, 70, 'small');
        $portion($piece, 150, 'large');

        $units = collect(app(KitchenIngredientCatalog::class)->all())->firstWhere('name', 'بصل')['units'];
        $byName = collect($units)->keyBy('name');

        $this->assertEqualsWithDelta(160, $byName['كوب']['grams'], 0.01);
        $this->assertNull($byName['حبة']['grams']);              // two sizes: the visitor picks one
        $this->assertSame([$small->id], array_slice(array_column($byName['حبة']['choices'], 'id'), 0, 1));
        $this->assertCount(2, $byName['حبة']['choices']);
    }
}
