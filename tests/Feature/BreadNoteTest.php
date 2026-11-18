<?php

namespace Tests\Feature;

use App\Models\FoodNutrientValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MealFixtures;
use Tests\TestCase;

/** A dish usually eaten with bread says its values are for the plate alone (the owner's decision 4b). */
class BreadNoteTest extends TestCase
{
    use MealFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_dish_eaten_with_bread_says_its_values_are_for_the_plate(): void
    {
        $this->setUpMeals();
        $hummus = $this->approvedMeal('حمص بطحينة', ['eaten_with_bread' => true]);
        $stew = $this->approvedMeal('ملوخية');

        $this->get(route('site.meals.show', $hummus))->assertOk()->assertSee('تؤكل عادةً مع الخبز، والأرقام هنا للصحن وحده');
        $this->get(route('site.meals.show', $stew))->assertOk()->assertDontSee('تؤكل عادةً مع الخبز');
    }

    public function test_the_bread_note_says_what_one_loaf_adds(): void
    {
        $this->setUpMeals();
        $bread = $this->ingredient('خبز عربي');
        foreach (['energy_kcal' => 275, 'carbohydrate_g' => 55.7] as $code => $amount) {
            FoodNutrientValue::create(['food_source_record_id' => $bread->id, 'nutrient_id' => $this->nutrients[$code]->id, 'amount_per_100g' => $amount]);
        }
        $hummus = $this->approvedMeal('حمص بطحينة', ['eaten_with_bread' => true]);

        // 60 g: 275 × 0.6 = 165 kcal, 55.7 × 0.6 ≈ 33 g.
        $this->get(route('site.meals.show', $hummus))->assertOk()
            ->assertSeeInOrder(['تؤكل عادةً مع الخبز', '60', '165', '33']);
    }
}
