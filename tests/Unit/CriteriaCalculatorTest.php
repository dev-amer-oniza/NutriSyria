<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

/** The design's example person: man, 30, 178 cm, 80 kg, moderately active, losing weight = 2240 kcal. */
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

    public function test_the_balanced_template_shares_the_designs_2240_kcal(): void
    {
        $sheet = $this->calculate();

        // 10×80 + 6.25×178 − 5×30 + 5 = 1767.5; ×1.55 = 2739.6; −500 = 2239.6
        $this->assertSame(['bmr' => 1768, 'tdee' => 2740, 'adjustment' => -500, 'floored' => false], $sheet->energy);
        $this->assertSame('balanced', $sheet->template['key']);
        $this->assertSame(['carbs' => 50, 'protein' => 20, 'fat' => 30], $sheet->template['macros']);
        $this->assertSame(CriterionSource::Default, $sheet->template['source']);
        $this->assertSame(
            ['energy_kcal' => 2240.0, 'carbohydrate_g' => 280.0, 'protein_g' => 112.0, 'fat_g' => 75.0,
                'fat_sat_g' => 25.0, 'sugars_g' => 56.0, 'fiber_g' => 31.0, 'sodium_mg' => 2000.0],
            $this->values($sheet, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g', 'fat_sat_g', 'sugars_g', 'fiber_g', 'sodium_mg']),
        );
        $this->assertSame('target', $sheet->get('protein_g')->bound);
        $this->assertSame(672.0, $sheet->get('energy_kcal')->perMeal($sheet->mealShare));
    }

    public function test_every_activity_level_uses_its_own_factor(): void
    {
        foreach (['sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55, 'very' => 1.725, 'extreme' => 1.9] as $level => $factor) {
            $this->assertSame((int) round(1767.5 * $factor), $this->calculate(['activity' => $level, 'goal' => 'maintain'])->energy['tdee'], $level);
        }
    }

    public function test_women_use_their_formula_and_never_go_below_the_safe_minimum(): void
    {
        $sheet = $this->calculate(['sex' => 'female', 'age' => 60, 'height_cm' => 150, 'weight_kg' => 45, 'activity' => 'sedentary']);

        // 10×45 + 6.25×150 − 5×60 − 161 = 926.5; ×1.2 = 1111.8; −500 → 1200
        $this->assertSame(927, $sheet->energy['bmr']);
        $this->assertTrue($sheet->energy['floored']);
        $this->assertSame(1200.0, $sheet->get('energy_kcal')->value);
    }

    public function test_gaining_adds_300_kcal_shared_by_the_template(): void
    {
        $this->assertSame(
            ['energy_kcal' => 3040.0, 'carbohydrate_g' => 380.0, 'protein_g' => 152.0, 'fat_g' => 101.0],
            $this->values($this->calculate(['goal' => 'gain']), ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']),
        );
    }

    public function test_a_condition_brings_its_template_and_its_rules(): void
    {
        $sheet = $this->calculate(['conditions' => ['diabetes']]);

        $this->assertSame('diabetes', $sheet->template['key']);
        $this->assertSame(
            ['carbohydrate_g' => 252.0, 'protein_g' => 112.0, 'fat_g' => 87.0, 'meal_carbohydrate_g' => 60.0, 'sugars_g' => 28.0],
            $this->values($sheet, ['carbohydrate_g', 'protein_g', 'fat_g', 'meal_carbohydrate_g', 'sugars_g']),
        );
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->perMeal($sheet->mealShare), 'a meal limit is not scaled');
    }

    public function test_the_higher_priority_template_wins_and_the_others_keep_their_limits(): void
    {
        $sheet = $this->calculate(['conditions' => ['diabetes', 'kidney']]);

        $this->assertSame('ckd', $sheet->template['key']);
        $this->assertSame('max', $sheet->get('protein_g')->bound, 'kidneys: protein becomes a ceiling');
        $this->assertSame(48.0, $sheet->get('protein_g')->value);            // 0.6 g/kg
        $this->assertSame(308.0, $sheet->get('carbohydrate_g')->value);      // 55%
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->value, 'from the diabetes template');
        $this->assertSame(28.0, $sheet->get('sugars_g')->value);
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
            ['carbohydrate_g' => 308.0, 'protein_g' => 101.0, 'fat_g' => 67.0, 'sodium_mg' => 1500.0, 'sugars_g' => 28.0, 'fat_sat_g' => 15.0],
            $this->values($sheet, ['carbohydrate_g', 'protein_g', 'fat_g', 'sodium_mg', 'sugars_g', 'fat_sat_g']),
        );
    }

    public function test_digestion_templates_lower_the_fat(): void
    {
        $gallbladder = $this->calculate(['conditions' => ['gallbladder_removed']]);
        $this->assertSame(['fat_g' => 62.0, 'meal_fat_g' => 20.0], $this->values($gallbladder, ['fat_g', 'meal_fat_g'])); // 25%

        $both = $this->calculate(['conditions' => ['gallbladder_removed', 'fat_intolerance']]);
        $this->assertSame(15.0, $both->get('meal_fat_g')->value, 'the stricter meal limit wins');
        $this->assertCount(1, $both->advice);

        $pancreas = $this->calculate(['conditions' => ['pancreatitis']]);
        $this->assertSame(['carbohydrate_g' => 364.0, 'fat_g' => 37.0, 'meal_fat_g' => 15.0], $this->values($pancreas, ['carbohydrate_g', 'fat_g', 'meal_fat_g']));

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
        $this->assertSame(84.0, $sheet->get('protein_g')->value);              // 15%
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
