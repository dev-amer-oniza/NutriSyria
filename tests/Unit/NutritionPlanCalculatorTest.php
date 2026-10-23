<?php

namespace Tests\Unit;

use App\Services\Nutrition\Plan\NutritionPlanCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The nutritionist's plan (decisions 1a 2a 3a). The person is the design's example man:
 * 30, 178 cm, 80 kg, moderately active; Mifflin gives 1768 kcal at rest, 2740 with activity.
 */
class NutritionPlanCalculatorTest extends TestCase
{
    private const MAN = ['sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80, 'conditions' => []];

    private const PLAN = [
        'equation' => 'mifflin', 'body_fat_pct' => null, 'activity' => 'moderate', 'adjustment' => -500,
        'carbs_pct' => 50, 'protein_pct' => 20, 'fat_pct' => 30, 'fluids_ml' => null,
    ];

    private function calculate(array $plan = [], array $profile = []): \App\Data\Nutrition\NutritionPlanResult
    {
        return (new NutritionPlanCalculator(require __DIR__.'/../../config/nutrition_criteria.php'))
            ->calculate($profile + self::MAN, $plan + self::PLAN);
    }

    public function test_the_standard_deficit_with_balanced_macros_matches_the_automatic_calculation(): void
    {
        $result = $this->calculate();

        $this->assertSame([1768, 2740, -500, 2240], [$result->bmr, $result->tdee, $result->adjustment, $result->target]);
        $this->assertSame(['pct' => 50, 'g' => 280, 'kcal' => 1120], $result->macros['carbs']);
        $this->assertSame(['pct' => 20, 'g' => 112, 'kcal' => 448], $result->macros['protein']);
        $this->assertSame(['pct' => 30, 'g' => 75, 'kcal' => 675], $result->macros['fat']);
        $this->assertSame(1.4, $result->proteinPerKg);
        $this->assertTrue($result->isValid());
    }

    public function test_each_equation(): void
    {
        $this->assertSame(1844, $this->calculate(['equation' => 'harris_benedict'])->bmr);
        $this->assertSame(1752, $this->calculate(['equation' => 'katch_mcardle', 'body_fat_pct' => 20])->bmr);  // 370 + 21.6 × 64 kg lean
        $this->assertSame(1325, $this->calculate(['equation' => 'harris_benedict'], ['sex' => 'female', 'weight_kg' => 60, 'height_cm' => 160, 'age' => 40])->bmr);
    }

    public function test_katch_mcardle_without_body_fat_cannot_be_saved(): void
    {
        $result = $this->calculate(['equation' => 'katch_mcardle']);

        $this->assertNull($result->target);
        $this->assertContains('معادلة Katch-McArdle تحتاج نسبة الدهون في الجسم.', $result->errors);
    }

    public function test_any_change_from_the_slider_and_the_strong_presets(): void
    {
        $this->assertSame(1990, $this->calculate(['adjustment' => -750])->target);
        $this->assertSame(3240, $this->calculate(['adjustment' => 500])->target);
        $this->assertSame(2390, $this->calculate(['adjustment' => -350])->target);
    }

    public function test_the_shares_must_make_100(): void
    {
        $result = $this->calculate(['carbs_pct' => 45, 'protein_pct' => 25, 'fat_pct' => 35]);

        $this->assertSame(105, $result->macroTotal());
        $this->assertFalse($result->isValid());
    }

    public function test_below_the_safe_minimum_or_a_deficit_in_pregnancy_is_a_warning_not_an_error(): void
    {
        $small = $this->calculate(['activity' => 'sedentary', 'adjustment' => -750], ['sex' => 'female', 'weight_kg' => 55, 'height_cm' => 155, 'age' => 50]);
        $this->assertTrue($small->isValid());
        $this->assertStringContainsString('أقل من الحد الأدنى الآمن', $small->warnings[0]);

        $pregnant = $this->calculate([], ['sex' => 'female', 'conditions' => ['pregnant_or_breastfeeding']]);
        $this->assertStringContainsString('الحمل', implode(' ', $pregnant->warnings));
    }

    public function test_fluids_are_suggested_unless_the_doctor_decides_them(): void
    {
        $this->assertSame(['ml' => 2800, 'suggested' => 2800, 'manual' => false], $this->calculate()->fluids);   // 35 ml × 80 kg
        $this->assertSame(2500, $this->calculate(['fluids_ml' => 2500])->fluids['ml']);

        $dialysis = $this->calculate([], ['conditions' => ['dialysis']]);
        $this->assertSame(['ml' => null, 'suggested' => null, 'manual' => true], $dialysis->fluids);
        $this->assertFalse($dialysis->isValid());
        $this->assertTrue($this->calculate(['fluids_ml' => 1000], ['conditions' => ['dialysis']])->isValid());
    }
    /** The standard five meals, filled by distribute() (2a). */
    private function standardMeals(): array
    {
        $types = [['breakfast', '08:00', 25], ['snack', '11:00', 10], ['lunch', '14:00', 35], ['snack', '17:00', 10], ['dinner', '20:00', 20]];
        $grams = NutritionPlanCalculator::distribute(['carbs' => 280, 'protein' => 112, 'fat' => 75], array_column($types, 2));

        return array_map(fn ($t, $g) => ['type' => $t[0], 'time' => $t[1], 'share_pct' => $t[2]] + $g, $types, $grams);
    }

    public function test_distribute_shares_whole_grams_that_add_up(): void
    {
        $split = NutritionPlanCalculator::distribute(['carbs' => 280, 'protein' => 112, 'fat' => 75], [25, 10, 35, 10, 20]);

        $this->assertSame([70, 28, 98, 28, 56], array_column($split, 'carbs_g'));
        $this->assertSame([28, 11, 39, 11, 23], array_column($split, 'protein_g'));
        $this->assertSame([19, 8, 26, 7, 15], array_column($split, 'fat_g'));
    }

    public function test_meals_get_their_calories_and_nothing_is_left(): void
    {
        $result = $this->calculate(['meals' => $this->standardMeals()]);

        $lunch = $result->meals[2];
        $this->assertSame(['lunch', 'غداء', 784, 782, -2], [$lunch['type'], $lunch['name'], $lunch['kcal'], $lunch['macro_kcal'], $lunch['diff']]);
        $this->assertSame(['share' => 0, 'carbs' => 0, 'protein' => 0, 'fat' => 0], $result->remaining);
        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->warnings);
    }

    public function test_meal_shares_must_make_100_but_grams_left_are_only_a_warning(): void
    {
        $meals = $this->standardMeals();
        $meals[0]['share_pct'] = 15;          // 90% in all
        $meals[2]['protein_g'] -= 34;

        $result = $this->calculate(['meals' => $meals]);

        $this->assertSame(10, $result->remaining['share']);
        $this->assertSame(34, $result->remaining['protein']);
        $this->assertContains('مجموع نسب الوجبات 90%، ويجب أن يكون 100%.', $result->errors);
        $this->assertStringContainsString('البروتين باقي 34 غ', $result->warnings[0]);

        $meals[0]['share_pct'] = 25;
        $this->assertTrue($this->calculate(['meals' => $meals])->isValid());
    }

    public function test_meals_are_ordered_by_time_and_a_custom_meal_keeps_its_name(): void
    {
        $meals = $this->standardMeals();
        $meals[] = ['type' => 'other', 'label' => 'بعد التمرين', 'time' => '09:30', 'share_pct' => 0, 'carbs_g' => 0, 'protein_g' => 0, 'fat_g' => 0];

        $names = array_column($this->calculate(['meals' => $meals])->meals, 'name');

        $this->assertSame(['فطور', 'بعد التمرين', 'سناك', 'غداء', 'سناك', 'عشاء'], $names);
    }

    public function test_a_plan_without_meals_stays_valid(): void
    {
        $result = $this->calculate(['meals' => []]);

        $this->assertNull($result->remaining);
        $this->assertTrue($result->isValid());
    }
}
