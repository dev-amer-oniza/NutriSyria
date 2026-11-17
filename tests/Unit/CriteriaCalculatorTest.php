<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

/** The example person: man, 30, 178 cm, 80 kg (BMI 25.2), moderately active, losing weight = 2590 kcal. */
class CriteriaCalculatorTest extends TestCase
{
    private function calculate(array $overrides = []): CriteriaSheet
    {
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';

        return (new CriteriaCalculator($rules))->calculate($overrides + [
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity' => 'moderate', 'goal' => 'lose', 'conditions' => [], 'allergens' => [],
        ]);
    }

    private function values(CriteriaSheet $sheet, array $keys): array
    {
        return array_map(fn (string $k) => $sheet->get($k)?->value, array_combine($keys, $keys));
    }

    public function test_the_balanced_template_shares_the_example_2590_kcal(): void
    {
        $sheet = $this->calculate();

        // 10×80 + 6.25×178 − 5×30 + 5 = 1767.5; ×1.75 (FAO active) = 3093.1; −500 = 2593.1
        $this->assertSame(['bmr' => 1768, 'tdee' => 3093, 'adjustment' => -500, 'floored' => false, 'held' => null, 'surplus' => 0], $sheet->energy);
        $this->assertSame('balanced', $sheet->template['key']);
        $this->assertSame(['carbs' => 50, 'protein' => 20, 'fat' => 30], $sheet->template['macros']);
        $this->assertSame(CriterionSource::Default, $sheet->template['source']);
        $this->assertSame(
            // 2590 × 50% ÷ 4, × 20% ÷ 4, × 30% ÷ 9, × 10% ÷ 9 (saturated), × 10% ÷ 4 (sugars), × 14 ÷ 1000;
            // the RDA for a man, 56 g.
            ['energy_kcal' => 2590.0, 'carbohydrate_g' => 324.0, 'protein_g' => 130.0, 'fat_g' => 86.0,
                'fat_sat_g' => 29.0, 'sugars_g' => 65.0, 'fiber_g' => 36.0, 'sodium_mg' => 2000.0, 'protein_min_g' => 56.0],
            $this->values($sheet, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g', 'fat_sat_g', 'sugars_g', 'fiber_g', 'sodium_mg', 'protein_min_g']),
        );
        $this->assertSame('target', $sheet->get('protein_g')->bound);
        $this->assertSame(777.0, $sheet->get('energy_kcal')->perMeal($sheet->mealShare));
    }

    public function test_every_activity_level_uses_its_own_factor(): void
    {
        foreach (['sedentary' => 1.40, 'light' => 1.55, 'moderate' => 1.75, 'very' => 2.00, 'extreme' => 2.20] as $level => $factor) {
            $this->assertSame((int) round(1767.5 * $factor), $this->calculate(['activity' => $level, 'goal' => 'maintain'])->energy['tdee'], $level);
        }
    }

    public function test_women_use_their_formula_and_never_go_below_the_safe_minimum(): void
    {
        $sheet = $this->calculate(['sex' => 'female', 'age' => 60, 'height_cm' => 150, 'weight_kg' => 60, 'activity' => 'sedentary']);

        // BMI 26.7; 10×60 + 6.25×150 − 5×60 − 161 = 1076.5; ×1.4 = 1507.1; −500 = 1007 → 1200
        $this->assertSame(1077, $sheet->energy['bmr']);
        $this->assertTrue($sheet->energy['floored']);
        $this->assertSame(1200.0, $sheet->get('energy_kcal')->value);
        $this->assertSame(46.0, $sheet->get('protein_min_g')->value, 'the RDA for a woman');
    }

    public function test_no_deficit_under_a_body_mass_index_of_25(): void
    {
        // 45 kg at 150 cm: BMI 20. The deficit's reference is for overweight and obesity.
        $sheet = $this->calculate(['sex' => 'female', 'age' => 60, 'height_cm' => 150, 'weight_kg' => 45, 'activity' => 'sedentary']);

        $this->assertSame(0, $sheet->energy['adjustment']);
        $this->assertSame('bmi', $sheet->energy['held']);
        $this->assertSame(1300.0, $sheet->get('energy_kcal')->value); // 926.5 × 1.4 = 1297
    }

    public function test_gaining_is_shown_but_judged_on_maintenance(): void
    {
        $sheet = $this->calculate(['goal' => 'gain']);

        // No guideline for +300 (decision 3a): the target stays at 3093, and 300 is only shown.
        $this->assertSame(['adjustment' => 0, 'surplus' => 300], array_intersect_key($sheet->energy, ['adjustment' => 0, 'surplus' => 0]));
        $this->assertSame(
            ['energy_kcal' => 3090.0, 'carbohydrate_g' => 386.0, 'protein_g' => 155.0, 'fat_g' => 103.0],
            $this->values($sheet, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']),
        );
    }

    public function test_a_condition_brings_its_template_and_its_rules(): void
    {
        $sheet = $this->calculate(['conditions' => ['diabetes']]);

        $this->assertSame('diabetes', $sheet->template['key']);
        $this->assertSame(
            ['carbohydrate_g' => 291.0, 'protein_g' => 130.0, 'fat_g' => 101.0, 'meal_carbohydrate_g' => 60.0, 'sugars_g' => 32.0],
            $this->values($sheet, ['carbohydrate_g', 'protein_g', 'fat_g', 'meal_carbohydrate_g', 'sugars_g']),
        );
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->perMeal($sheet->mealShare), 'a meal limit is not scaled');
    }

    public function test_jaundice_of_unknown_cause_takes_the_liver_template_and_sends_to_a_nutritionist(): void
    {
        $jaundice = $this->calculate(['conditions' => ['jaundice']]);
        $liver = $this->calculate(['conditions' => ['liver_cirrhosis']]);

        $this->assertSame('hepatic', $jaundice->template['key']);
        $this->assertSame(96.0, $jaundice->get('protein_g')->value, 'protein is a floor of 1.2 g/kg, never cut');
        $this->assertSame(
            $this->values($liver, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g', 'sodium_mg']),
            $this->values($jaundice, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g', 'sodium_mg']),
        );
        $this->assertCount(2, $jaundice->advice, 'the liver advice and the "find the cause" advice');
    }

    public function test_the_higher_priority_template_wins_and_the_others_keep_their_limits(): void
    {
        $sheet = $this->calculate(['conditions' => ['diabetes', 'kidney']]);

        $this->assertSame('ckd', $sheet->template['key']);
        $this->assertSame('max', $sheet->get('protein_g')->bound, 'kidneys: protein becomes a ceiling');
        $this->assertSame(48.0, $sheet->get('protein_g')->value);            // 0.6 g/kg
        $this->assertSame(356.0, $sheet->get('carbohydrate_g')->value);      // 55%
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->value, 'from the diabetes template');
        $this->assertSame(32.0, $sheet->get('sugars_g')->value);
        $this->assertNull($sheet->get('protein_min_g'), 'no RDA floor above the kidneys\' protein ceiling');
        $this->assertCount(1, $sheet->advice);

        $dialysis = $this->calculate(['conditions' => ['kidney', 'dialysis']]);
        $this->assertSame('dialysis', $dialysis->template['key']);
        $this->assertSame('min', $dialysis->get('protein_g')->bound, "a lower template's ceiling never flips the winner's floor");
        $this->assertSame(96.0, $dialysis->get('protein_g')->value);         // 1.2 g/kg

        $bp = $this->calculate(['conditions' => ['diabetes', 'hypertension']]);
        $this->assertSame('diabetes', $bp->template['key']);
        $this->assertSame(1500.0, $bp->get('sodium_mg')->value, "DASH's sodium limit still applies");
    }

    public function test_conditions_add_their_own_limits_and_the_stricter_wins(): void
    {
        $sheet = $this->calculate(['conditions' => ['hypertension', 'fatty_liver', 'heart_disease']]);

        $this->assertSame('dash', $sheet->template['key']);
        $this->assertSame(
            ['carbohydrate_g' => 356.0, 'protein_g' => 117.0, 'fat_g' => 78.0, 'sodium_mg' => 1500.0, 'sugars_g' => 32.0, 'fat_sat_g' => 17.0],
            $this->values($sheet, ['carbohydrate_g', 'protein_g', 'fat_g', 'sodium_mg', 'sugars_g', 'fat_sat_g']),
        );
    }

    public function test_digestion_templates_lower_the_fat(): void
    {
        $gallbladder = $this->calculate(['conditions' => ['gallbladder_removed']]);
        $this->assertSame(['fat_g' => 72.0, 'meal_fat_g' => 20.0], $this->values($gallbladder, ['fat_g', 'meal_fat_g'])); // 25%

        $both = $this->calculate(['conditions' => ['gallbladder_removed', 'fat_intolerance']]);
        $this->assertSame(15.0, $both->get('meal_fat_g')->value, 'the stricter meal limit wins');
        $this->assertCount(1, $both->advice);

        $pancreas = $this->calculate(['conditions' => ['pancreatitis']]);
        $this->assertSame(['carbohydrate_g' => 421.0, 'fat_g' => 43.0, 'meal_fat_g' => 15.0], $this->values($pancreas, ['carbohydrate_g', 'fat_g', 'meal_fat_g']));

        $this->assertSame(['gluten'], $this->calculate(['conditions' => ['celiac']])->allergens, 'celiac avoids gluten');
    }

    public function test_women_only_templates_and_minerals(): void
    {
        $pregnant = $this->calculate(['sex' => 'female', 'conditions' => ['pregnant_or_breastfeeding']]);
        $this->assertSame(0, $pregnant->energy['adjustment'], 'no weight-loss deficit');
        $this->assertSame(27.0, $pregnant->get('iron_mg')->value);
        $this->assertCount(1, $pregnant->advice);

        $gdm = $this->calculate(['sex' => 'female', 'conditions' => ['gestational_diabetes', 'pregnant_or_breastfeeding']]);
        $this->assertSame('gdm', $gdm->template['key']);
        $this->assertSame(45.0, $gdm->get('meal_carbohydrate_g')->value);

        $this->assertSame(18.0, $this->calculate(['sex' => 'female', 'conditions' => ['anemia']])->get('iron_mg')->value, 'not rounded to 20');
        $this->assertSame(8.0, $this->calculate(['conditions' => ['anemia']])->get('iron_mg')->value);
        $this->assertSame(1200.0, $this->calculate(['conditions' => ['osteoporosis']])->get('calcium_mg')->value);
        $this->assertSame(25, $this->calculate(['sex' => 'female', 'conditions' => ['menopause']])->template['macros']['protein']);
    }

    public function test_a_chosen_template_replaces_the_automatic_one_but_not_the_conditions_limits(): void
    {
        $sheet = $this->calculate(['conditions' => ['diabetes'], 'template' => 'mediterranean', 'template_source' => CriterionSource::Nutritionist]);

        $this->assertSame('mediterranean', $sheet->template['key']);
        $this->assertSame(CriterionSource::Nutritionist, $sheet->template['source']);
        $this->assertSame(97.0, $sheet->get('protein_g')->value);              // 2590 × 15% ÷ 4
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->value, "diabetes' meal limit stays");
    }

    public function test_criteria_without_a_condition_stay_off(): void
    {
        $sheet = $this->calculate();

        foreach (['meal_fat_g', 'iron_mg', 'calcium_mg', 'meal_carbohydrate_g'] as $key) {
            $this->assertNull($sheet->get($key), $key);
        }
        $this->assertSame([], $sheet->advice);
    }

    public function test_every_template_shares_all_the_calories(): void
    {
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';

        foreach ($rules['templates'] as $key => $template) {
            $this->assertSame(100, array_sum($template['macros']), $key);
            $this->assertContains($key, $rules['template_priority'], "$key has a priority");
        }
        foreach ($rules['conditions'] as $key => $condition) {
            if (isset($condition['template'])) {
                $this->assertArrayHasKey($condition['template'], $rules['templates'], $key);
            }
        }
    }
}
