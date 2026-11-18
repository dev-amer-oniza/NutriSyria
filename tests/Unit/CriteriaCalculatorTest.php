<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

/** The example person: man, 30, 178 cm, 80 kg (BMI 25.2), moderately active, losing weight = 2590 kcal. */
class CriteriaCalculatorTest extends TestCase
{
    /** Rules laid over the config: a fixture for the engine, not the rules themselves. */
    private array $patch = [];

    private function calculate(array $overrides = []): CriteriaSheet
    {
        $rules = array_replace_recursive(require __DIR__.'/../../config/nutrition_criteria.php', $this->patch);

        return (new CriteriaCalculator($rules))->calculate($overrides + [
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity' => 'moderate', 'goal' => 'lose', 'allergens' => [],
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
        $this->assertSame(['bmr' => 1768, 'tdee' => 3093, 'adjustment' => -500, 'floored' => false], $sheet->energy);
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

    public function test_a_normal_weight_may_lose_too(): void
    {
        // 45 kg at 150 cm: BMI 20. The deficit applies (decision 2a); the floor still holds.
        $sheet = $this->calculate(['sex' => 'female', 'age' => 60, 'height_cm' => 150, 'weight_kg' => 45, 'activity' => 'sedentary']);

        $this->assertSame(-500, $sheet->energy['adjustment']);
        $this->assertTrue($sheet->energy['floored']);                 // 926.5 × 1.4 − 500 = 797
        $this->assertSame(1200.0, $sheet->get('energy_kcal')->value);
    }

    public function test_gaining_adds_300_and_counts(): void
    {
        $sheet = $this->calculate(['goal' => 'gain']);

        // +300 counts like the rest (decision 4a): 3093.1 + 300 = 3393 → 3390.
        $this->assertSame(300, $sheet->energy['adjustment']);
        $this->assertSame(
            ['energy_kcal' => 3390.0, 'carbohydrate_g' => 424.0, 'protein_g' => 170.0, 'fat_g' => 113.0],
            $this->values($sheet, ['energy_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']),
        );
    }

    public function test_a_nutritionists_template_brings_its_shares_and_its_rules(): void
    {
        $this->patch = ['templates' => ['low_protein' => [
            'label' => 'تجربة', 'group' => 'general', 'description' => '',
            'macros' => ['carbs' => 55, 'protein' => 15, 'fat' => 30],
            'rules' => ['protein_g' => ['type' => 'per_kg', 'value' => 0.6, 'bound' => 'max']],
        ]]];
        $sheet = $this->calculate(['template' => 'low_protein', 'template_source' => CriterionSource::Nutritionist]);

        $this->assertSame(['low_protein', CriterionSource::Nutritionist], [$sheet->template['key'], $sheet->template['source']]);
        $this->assertSame(356.0, $sheet->get('carbohydrate_g')->value);   // 2590 × 55% ÷ 4
        $this->assertSame(['max', 48.0], [$sheet->get('protein_g')->bound, $sheet->get('protein_g')->value]); // 0.6 × 80
        $this->assertNull($sheet->get('protein_min_g'), 'no RDA floor (56 g) above a protein ceiling (48 g)');
    }

    public function test_an_unknown_template_falls_back_to_the_default(): void
    {
        $this->assertSame('balanced', $this->calculate(['template' => 'gone', 'template_source' => CriterionSource::Nutritionist])->template['key']);
    }

    public function test_criteria_without_a_default_stay_off(): void
    {
        $sheet = $this->calculate();

        foreach (['meal_fat_g', 'iron_mg', 'calcium_mg', 'meal_carbohydrate_g'] as $key) {
            $this->assertNull($sheet->get($key), $key);
        }
    }

    public function test_every_template_shares_all_the_calories(): void
    {
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';

        foreach ($rules['templates'] as $key => $template) {
            $this->assertSame(100, array_sum($template['macros']), $key);
        }
        $this->assertArrayHasKey($rules['default_template'], $rules['templates']);
    }
}
