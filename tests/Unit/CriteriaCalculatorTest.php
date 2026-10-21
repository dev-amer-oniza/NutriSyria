<?php

namespace Tests\Unit;

use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

class CriteriaCalculatorTest extends TestCase
{
    private function calculate(array $overrides = [])
    {
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';

        return (new CriteriaCalculator($rules))->calculate($overrides + [
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity' => 'moderate', 'goal' => 'lose', 'conditions' => [], 'allergens' => [],
        ]);
    }

    public function test_the_design_example_gives_2240_kcal_and_96_g_protein(): void
    {
        $sheet = $this->calculate();

        // 10×80 + 6.25×178 − 5×30 + 5 = 1767.5; ×1.55 = 2739.6; −500 = 2239.6
        $this->assertSame(['bmr' => 1768, 'tdee' => 2740, 'adjustment' => -500, 'floored' => false], $sheet->energy);
        $this->assertSame(2240.0, $sheet->get('energy_kcal')->value);
        $this->assertSame(96.0, $sheet->get('protein_g')->value);      // 1.2 g/kg
        $this->assertSame(87.0, $sheet->get('fat_g')->value);          // 35% / 9
        $this->assertSame(25.0, $sheet->get('fat_sat_g')->value);      // 10% / 9
        $this->assertSame(364.0, $sheet->get('carbohydrate_g')->value); // 65% / 4
        $this->assertSame(56.0, $sheet->get('sugars_g')->value);       // 10% / 4
        $this->assertSame(31.0, $sheet->get('fiber_g')->value);        // 14 per 1000
        $this->assertSame(2000.0, $sheet->get('sodium_mg')->value);
        $this->assertNull($sheet->get('meal_carbohydrate_g'));
        $this->assertSame(0.30, $sheet->mealShare);
        $this->assertSame(672.0, $sheet->get('energy_kcal')->perMeal($sheet->mealShare));
        $this->assertSame(CriterionSource::Default, $sheet->get('protein_g')->source);
    }

    public function test_every_activity_level_uses_its_own_factor(): void
    {
        $bmr = 1767.5;
        foreach (['sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55, 'very' => 1.725, 'extreme' => 1.9] as $level => $factor) {
            $this->assertSame((int) round($bmr * $factor), $this->calculate(['activity' => $level, 'goal' => 'maintain'])->energy['tdee'], $level);
        }
    }

    public function test_women_use_their_formula_and_never_go_below_the_safe_minimum(): void
    {
        $sheet = $this->calculate(['sex' => 'female', 'age' => 60, 'height_cm' => 150, 'weight_kg' => 45, 'activity' => 'sedentary']);

        // 10×45 + 6.25×150 − 5×60 − 161 = 926.5; ×1.2 = 1111.8; −500 = 611.8 → 1200
        $this->assertSame(927, $sheet->energy['bmr']);
        $this->assertTrue($sheet->energy['floored']);
        $this->assertSame(1200.0, $sheet->get('energy_kcal')->value);
    }

    public function test_gaining_adds_300_kcal_and_more_protein(): void
    {
        $sheet = $this->calculate(['goal' => 'gain']);

        $this->assertSame(3040.0, $sheet->get('energy_kcal')->value); // 2739.6 + 300
        $this->assertSame(128.0, $sheet->get('protein_g')->value);     // 1.6 g/kg
    }

    public function test_conditions_tighten_criteria_and_the_stricter_rule_wins(): void
    {
        $sheet = $this->calculate(['conditions' => ['hypertension', 'diabetes', 'high_cholesterol', 'kidney']]);

        $this->assertSame(1500.0, $sheet->get('sodium_mg')->value, 'hypertension 1500 beats kidney 2000');
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->value);
        $this->assertSame('meal', $sheet->get('meal_carbohydrate_g')->scope);
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->perMeal($sheet->mealShare), 'a meal limit is not scaled');
        $this->assertSame(28.0, $sheet->get('sugars_g')->value);      // 5% / 4
        $this->assertSame(15.0, $sheet->get('fat_sat_g')->value);     // 6% / 9
        $this->assertSame('max', $sheet->get('protein_g')->bound, 'kidneys turn protein into a ceiling');
        $this->assertSame(64.0, $sheet->get('protein_g')->value);     // 0.8 g/kg
        $this->assertCount(1, $sheet->advice);
    }

    public function test_pregnancy_keeps_the_deficit_off_and_advises_a_nutritionist(): void
    {
        $sheet = $this->calculate(['sex' => 'female', 'conditions' => ['pregnant_or_breastfeeding']]);

        $this->assertSame(0, $sheet->energy['adjustment']);
        $this->assertSame((float) round($sheet->energy['tdee'] / 10) * 10, $sheet->get('energy_kcal')->value);
        $this->assertCount(1, $sheet->advice);
    }

    public function test_allergens_are_carried_to_the_sheet(): void
    {
        $this->assertSame(['sesame', 'eggs'], $this->calculate(['allergens' => ['sesame', 'eggs']])->allergens);
    }

    public function test_gallbladder_and_fat_intolerance_lower_the_fat_per_day_and_per_meal(): void
    {
        $sheet = $this->calculate(['conditions' => ['gallbladder_removed']]);

        $this->assertSame(75.0, $sheet->get('fat_g')->value);       // 30% of 2240 / 9, down from 35%
        $this->assertSame(20.0, $sheet->get('meal_fat_g')->value);
        $this->assertSame(20.0, $sheet->get('meal_fat_g')->perMeal($sheet->mealShare), 'a meal limit is not scaled');
        $this->assertSame([], $sheet->advice);

        $both = $this->calculate(['conditions' => ['gallbladder_removed', 'fat_intolerance']]);
        $this->assertSame(15.0, $both->get('meal_fat_g')->value, 'the stricter meal limit wins');
        $this->assertCount(1, $both->advice);
    }

    public function test_new_conditions_set_their_criteria(): void
    {
        $anemicWoman = $this->calculate(['sex' => 'female', 'conditions' => ['anemia']]);
        $this->assertSame(18.0, $anemicWoman->get('iron_mg')->value, 'not rounded to 20');
        $this->assertSame(8.0, $this->calculate(['conditions' => ['anemia']])->get('iron_mg')->value);
        $this->assertSame(1200.0, $this->calculate(['conditions' => ['osteoporosis']])->get('calcium_mg')->value);

        $sheet = $this->calculate(['conditions' => ['heart_disease', 'fatty_liver', 'insulin_resistance', 'reflux']]);
        $this->assertSame(1500.0, $sheet->get('sodium_mg')->value);
        $this->assertSame(15.0, $sheet->get('fat_sat_g')->value);       // heart 6% beats liver 7%
        $this->assertSame(28.0, $sheet->get('sugars_g')->value);        // 5%
        $this->assertSame(252.0, $sheet->get('carbohydrate_g')->value); // 45%
        $this->assertSame(20.0, $sheet->get('meal_fat_g')->value);
        $this->assertCount(1, $this->calculate(['conditions' => ['gout']])->advice);
    }

    public function test_criteria_without_a_condition_stay_off(): void
    {
        $sheet = $this->calculate();

        foreach (['meal_fat_g', 'iron_mg', 'calcium_mg', 'meal_carbohydrate_g'] as $key) {
            $this->assertNull($sheet->get($key), $key);
        }
    }
}
