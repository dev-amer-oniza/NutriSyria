<?php

namespace Tests\Feature;

use App\Data\Nutrition\RecipeDraft;
use App\Data\Nutrition\RecipeLine;
use App\Exceptions\RecipeRejected;
use App\Models\Brand;
use App\Models\FoodPortion;
use App\Models\MeasureUnit;
use App\Models\Nutrient;
use App\Services\Nutrition\RecipeAnalysisService;
use App\Services\Site\Kitchen\CustomRecipeAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\KitchenRecipeFixtures;
use Tests\TestCase;

/**
 * "اطبخها على طريقتك", phase 1: a visitor's version of a kitchen dish goes through the panel's
 * very analysis, with a fixed number of queries, and nothing is ever written.
 */
class CustomRecipeAnalyzerTest extends TestCase
{
    use KitchenRecipeFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKitchenRecipe();
    }

    private function analyzer(): CustomRecipeAnalyzer
    {
        return app(CustomRecipeAnalyzer::class);
    }

    public function test_our_own_recipe_gives_our_own_numbers(): void
    {
        $result = $this->analyzer()->analyze($this->dish->id, $this->draft([[$this->lentils, 280, false], [$this->oil, 20, true]], after: 240), null);

        $this->assertEqualsWithDelta($result->original->amount('energy_kcal'), $result->meal->amount('energy_kcal'), 0.01);
        $this->assertEqualsWithDelta(120, $result->meal->servingGrams, 0.01);
        $this->assertFalse($result->weightEstimated);
        $this->assertSame(['عدس', 'زيت زيتون'], $result->meal->ingredients);
    }

    public function test_their_changes_change_the_numbers(): void
    {
        // Half the oil, three servings.
        $result = $this->analyzer()->analyze($this->dish->id, $this->draft([[$this->lentils, 280, false], [$this->oil, 10, true]], servings: 3, after: 230), null);

        // (280 × 1.16 + 10 × 8.84) / 3 servings
        $this->assertEqualsWithDelta((280 * 1.16 + 10 * 8.84) / 3, $result->meal->amount('energy_kcal'), 0.01);
        $this->assertLessThan($result->original->amount('fat_g'), $result->meal->amount('fat_g'));
    }

    public function test_an_unweighed_dish_loses_what_ours_loses_in_cooking(): void
    {
        // Ours: (240 - 20) / 280 of the cooked part stays. Theirs: 560 g cooked, 20 g on top.
        $result = $this->analyzer()->analyze($this->dish->id, $this->draft([[$this->lentils, 560, false], [$this->oil, 20, true]]), null);

        $this->assertTrue($result->weightEstimated);
        $this->assertEqualsWithDelta(560 * 220 / 280 + 20, $result->weightAfterCookingG, 0.1);
    }

    public function test_only_approved_ingredients_and_kitchen_dishes(): void
    {
        $pending = $this->record('سمنة', ['fat_g' => 99], status: 'pending_review');
        try {
            $this->analyzer()->analyze($this->dish->id, $this->draft([[$pending, 20, false]]), null);
            $this->fail('A pending record was analysed.');
        } catch (RecipeRejected $e) {
            $this->assertStringContainsString('غير متوفرة في قاعدتنا المعتمدة', $e->getMessage());
        }

        $restaurant = $this->kitchenDish([[$this->lentils, 100, false]], servings: 1, after: 100, brand: Brand::create(['name' => 'مطعم']));
        $this->expectException(RecipeRejected::class);
        $this->analyzer()->analyze($restaurant->id, $this->draft([[$this->lentils, 100, false]]), null);
    }

    public function test_nothing_is_written(): void
    {
        $this->analyzer()->analyze($this->dish->id, $this->draft([[$this->lentils, 280, false]]), null); // warm the caches
        $before = [DB::table('food_nutrient_values')->count(), DB::table('product_recipe_ingredients')->count(), DB::table('product_recipes')->max('updated_at')];

        DB::enableQueryLog();
        $this->analyzer()->analyze($this->dish->id, $this->draft([[$this->lentils, 300, false], [$this->oil, 5, true]], after: 260), null);
        $writes = array_filter(DB::getQueryLog(), fn ($q) => ! str_starts_with(strtolower(ltrim($q['query'])), 'select'));

        $this->assertSame([], array_values($writes));
        $this->assertSame($before, [DB::table('food_nutrient_values')->count(), DB::table('product_recipe_ingredients')->count(), DB::table('product_recipes')->max('updated_at')]);
    }

    public function test_the_queries_do_not_grow_with_the_lines(): void
    {
        $service = app(RecipeAnalysisService::class);
        $service->analyzeDraft($this->draft([[$this->lentils, 100, false]], after: 100)); // warm the nutrient list

        $cup = MeasureUnit::create(['code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household']);

        // Each extra line by the cup, so its record and portions must be read too.
        $count = function (int $lines) use ($service, $cup): int {
            $extra = array_map(function ($i) use ($cup) {
                $record = $this->record("مكوّن {$i}", ['energy_kcal' => 100]);
                FoodPortion::create(['food_id' => $record->food_id, 'food_source_record_id' => $record->id,
                    'measure_unit_id' => $cup->id, 'amount' => 1, 'gram_weight' => 150]);

                return new RecipeLine($record->id, $cup->id, 1);
            }, range(1, $lines));
            DB::flushQueryLog();
            DB::enableQueryLog();
            $result = $service->analyzeDraft(new RecipeDraft([new RecipeLine($this->lentils->id, $this->g->id, 100), ...$extra], 1, null, 100));
            $this->assertSame([], $result->unconvertedIngredients);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->assertSame($count(2), $count(20));
    }
}
