<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

/** The example person: man, 30, 178 cm, 80 kg (BMI 25.2), moderately active, losing weight = 2590 kcal. */
class CriteriaCalculatorTest extends TestCase
{
    /** Rules laid over the config: a fixture for the engine, not the medical rules themselves. */
    private array $patch = [];

    /**
     * Diabetes as it was before step 7 found no figure for it: a condition bringing a template
     * whose rules are not verified yet. The engine's handling of such rules is tested on it.
     */
    private function pendingDiabetes(): void
    {
        $this->patch = [
            'conditions' => ['diabetes' => ['template' => 'diabetes']],
            'templates' => ['diabetes' => ['label' => 'السكري (تحكّم بالكربوهيدرات)', 'rules' => [
                'meal_carbohydrate_g' => ['type' => 'fixed', 'value' => 60],
                'sugars_g' => ['type' => 'energy_pct', 'pct' => 5, 'kcal_per_g' => 4],
            ]]],
        ];
    }

    private function calculate(array $overrides = []): CriteriaSheet
    {
        $rules = array_replace_recursive(require __DIR__.'/../../config/nutrition_criteria.php', $this->patch);

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
        $this->assertSame(['bmr' => 1768, 'tdee' => 3093, 'adjustment' => -500, 'floored' => false, 'held' => null, 'held_why' => null, 'surplus' => 0, 'raised' => null, 'raised_by' => null], $sheet->energy);
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

    /**
     * The template's or condition's own rule as written, not verified yet (step 2): the
     * judging value's pending rule, or the criterion itself when nothing verified covers it.
     */
    private function pending(CriteriaSheet $sheet, array $keys): array
    {
        return array_map(function (string $k) use ($sheet) {
            $c = $sheet->get($k);

            return $c?->pending?->value ?? ($c && ! $c->verified ? $c->value : null);
        }, array_combine($keys, $keys));
    }

    public function test_a_condition_brings_its_template_and_its_rules(): void
    {
        $this->pendingDiabetes();
        $sheet = $this->calculate(['conditions' => ['diabetes']]);

        $this->assertSame('diabetes', $sheet->template['key']);
        $this->assertSame(['carbs' => 45, 'protein' => 20, 'fat' => 35], $sheet->template['macros']);
        // Not verified yet: the balanced shares judge (decision 1a), the template is shown.
        $this->assertFalse($sheet->template['verified']);
        $this->assertSame('المتوازن', $sheet->template['judging']);
        $this->assertSame(
            ['carbohydrate_g' => 324.0, 'protein_g' => 130.0, 'fat_g' => 86.0],
            $this->values($sheet, ['carbohydrate_g', 'protein_g', 'fat_g']),
        );
        $this->assertSame(['meal_carbohydrate_g' => 60.0, 'sugars_g' => 32.0], $this->pending($sheet, ['meal_carbohydrate_g', 'sugars_g']));
        $this->assertFalse($sheet->get('meal_carbohydrate_g')->verified, 'shown, never judged');
        $this->assertSame('قالب السكري (تحكّم بالكربوهيدرات)', $sheet->get('meal_carbohydrate_g')->origin);
        $this->assertSame(60.0, $sheet->get('meal_carbohydrate_g')->perMeal($sheet->mealShare), 'a meal limit is not scaled');
        $this->assertCount(1, $sheet->pendingConditions);
    }

    public function test_the_liver_conditions_follow_their_references(): void
    {
        $base = $this->calculate();

        // Acute (A, E) and chronic (B, C) hepatitis, jaundice and the old mixed choice: no limits
        // of their own, the general needs, nothing pending (step 3).
        foreach (['hepatitis_acute', 'hepatitis_chronic', 'jaundice', 'liver_cirrhosis'] as $condition) {
            $sheet = $this->calculate(['conditions' => [$condition]]);
            $this->assertSame('balanced', $sheet->template['key'], $condition);
            $this->assertSame($this->values($base, ['energy_kcal', 'protein_g', 'protein_min_g', 'sodium_mg']),
                $this->values($sheet, ['energy_kcal', 'protein_g', 'protein_min_g', 'sodium_mg']), $condition);
            $this->assertSame([], $sheet->pendingConditions, $condition);
        }
        $this->assertCount(1, $this->calculate(['conditions' => ['jaundice']])->advice, '"find the cause"');

        // Cirrhosis: protein at least 1.2 g/kg, verified; at least 35 kcal/kg and no deficit under a BMI of 30.
        $cirrhosis = $this->calculate(['conditions' => ['cirrhosis']]);
        $this->assertSame(96.0, $cirrhosis->get('protein_min_g')->value);            // 80 × 1.2
        $this->assertTrue($cirrhosis->get('protein_min_g')->verified);
        $this->assertSame('تليّف الكبد', $cirrhosis->get('protein_min_g')->origin);
        $this->assertSame(['adjustment' => 0, 'held' => 'condition'], array_intersect_key($cirrhosis->energy, ['adjustment' => 0, 'held' => 0]));
        $this->assertSame(3090.0, $cirrhosis->get('energy_kcal')->value);           // 3093 > 35 × 80 = 2800
        $this->assertSame([], $cirrhosis->pendingConditions);

        // A light, sedentary person (BMI 18.9): 35 × 60 = 2100 raises 1417.5 × 1.4 = 1985.
        $raised = $this->calculate(['conditions' => ['cirrhosis'], 'weight_kg' => 60, 'activity' => 'sedentary', 'age' => 60]);
        $this->assertSame([35, 'تليّف الكبد'], [$raised->energy['raised'], $raised->energy['raised_by']]);
        $this->assertSame(2100.0, $raised->get('energy_kcal')->value);

        // Obese (BMI 31.6): the general rules, deficit included (ESPEN 51).
        $obese = $this->calculate(['conditions' => ['cirrhosis'], 'weight_kg' => 100]);
        $this->assertSame(-500, $obese->energy['adjustment']);
        $this->assertNull($obese->energy['raised']);

        // Fatty liver: no figure for saturated fat or sugars in its reference.
        $fatty = $this->calculate(['conditions' => ['fatty_liver']]);
        $this->assertSame([null, null], [$fatty->get('fat_sat_g')->pending, $fatty->get('sugars_g')->pending]);
        $this->assertSame(-500, $fatty->energy['adjustment']);
        $this->assertSame([], $fatty->pendingConditions);
    }

    public function test_the_higher_priority_template_wins_and_the_others_keep_their_limits(): void
    {
        $this->pendingDiabetes();
        $sheet = $this->calculate(['conditions' => ['diabetes', 'kidney']]);

        $this->assertSame('ckd', $sheet->template['key']);
        $this->assertSame(['max', 64.0, true], [$sheet->get('protein_g')->bound, $sheet->get('protein_g')->value, $sheet->get('protein_g')->verified],
            'kidneys: protein becomes KDIGO\'s ceiling, 0.8 g/kg');
        $this->assertSame(['meal_carbohydrate_g' => 60.0, 'sugars_g' => 32.0],     // from the diabetes template
            $this->pending($sheet, ['meal_carbohydrate_g', 'sugars_g']));
        $this->assertSame(56.0, $sheet->get('protein_min_g')->value, 'the RDA floor stays under the 64 g ceiling');
        $this->assertCount(2, $sheet->advice, 'the kidneys\' and diabetes\' (its carbohydrate is the doctor\'s number)');
        $this->assertCount(1, $sheet->pendingConditions, 'only diabetes: the kidneys\' protein is verified now');

        $dialysis = $this->calculate(['conditions' => ['kidney', 'dialysis']]);
        $this->assertSame('dialysis', $dialysis->template['key']);
        $this->assertSame('min', $dialysis->get('protein_g')->pending->bound, "a lower template's ceiling never flips the winner's floor");
        $this->assertSame(['protein_g' => 96.0], $this->pending($dialysis, ['protein_g']));        // 1.2 g/kg, KDOQI not read yet
        $this->assertNotSame('max', $dialysis->get('protein_g')->bound, 'KDIGO\'s CKD ceiling is not for dialysis');

        $small = $this->calculate(['conditions' => ['kidney'], 'sex' => 'female', 'weight_kg' => 50]);
        $this->assertNull($small->get('protein_min_g'), 'no RDA floor (46 g) above the kidneys\' ceiling (40 g)');

        $bp = $this->calculate(['conditions' => ['diabetes', 'hypertension']]);
        $this->assertSame('diabetes', $bp->template['key']);
        $this->assertSame(1500.0, $bp->get('sodium_mg')->value, "DASH's verified sodium limit still comes along, and the stricter wins");
        $this->assertTrue($bp->get('sodium_mg')->verified);
    }

    public function test_conditions_add_their_own_limits_and_the_stricter_wins(): void
    {
        $sheet = $this->calculate(['conditions' => ['hypertension', 'fatty_liver', 'heart_disease']]);

        $this->assertSame('dash', $sheet->template['key']);
        $this->assertSame(['sodium_mg' => null, 'sugars_g' => null, 'fat_sat_g' => null], $this->pending($sheet, ['sodium_mg', 'sugars_g', 'fat_sat_g']));
        // DASH's 6% is stricter than heart disease's 7% (NICE); both verified.
        $this->assertSame(['sodium_mg' => 1500.0, 'fat_sat_g' => 17.0], $this->values($sheet, ['sodium_mg', 'fat_sat_g']));

        $heart = $this->calculate(['conditions' => ['heart_disease']]);
        $this->assertSame('balanced', $heart->template['key'], 'heart disease brings no template');
        $this->assertSame(20.0, $heart->get('fat_sat_g')->value);   // 7% of 2593 kcal ÷ 9
        $this->assertTrue($heart->get('fat_sat_g')->verified);

        $failure = $this->calculate(['conditions' => ['heart_failure']]);
        $this->assertSame(['balanced', 2000.0], [$failure->template['key'], $failure->get('sodium_mg')->value], 'heart failure: no routine sodium limit');
        $this->assertSame([], $failure->pendingConditions);
    }

    public function test_a_nutritionists_template_judges_with_its_own_rules(): void
    {
        $sheet = $this->calculate(['conditions' => ['hypertension'], 'template' => 'dash', 'template_source' => CriterionSource::Nutritionist]);

        $this->assertTrue($sheet->template['verified']);
        $this->assertSame(['sodium_mg' => 1500.0, 'carbohydrate_g' => 356.0], $this->values($sheet, ['sodium_mg', 'carbohydrate_g'])); // 55%
        $this->assertTrue($sheet->get('sodium_mg')->verified);
        $this->assertSame([], $sheet->pendingConditions);
    }

    public function test_digestion_conditions_bring_their_references_advice_and_no_fat_figure(): void
    {
        // Step 7, batch 1: no reference gives a fat figure (and two advise against restricting
        // it): the general needs judge, verified, and the doctor's number when there is one.
        foreach (['reflux', 'gallbladder_removed', 'fat_intolerance', 'pancreatitis'] as $condition) {
            $sheet = $this->calculate(['conditions' => [$condition]]);
            $this->assertNull($sheet->get('meal_fat_g'), $condition);
            $this->assertSame('balanced', $sheet->template['key'], $condition);
            $this->assertSame([], $sheet->pendingConditions, $condition);
            $this->assertSame(86.0, $sheet->get('fat_g')->value, $condition);
        }
        $this->assertStringContainsString('اكتبه في معاييري', $this->calculate(['conditions' => ['fat_intolerance']])->advice[0]);
        $this->assertStringContainsString('أخصائي تغذية', $this->calculate(['conditions' => ['pancreatitis']])->advice[0]);

        $this->assertSame(['gluten'], $this->calculate(['conditions' => ['celiac']])->allergens, 'celiac avoids gluten');
    }

    public function test_women_only_templates_and_minerals(): void
    {
        $pregnant = $this->calculate(['sex' => 'female', 'conditions' => ['pregnant_or_breastfeeding']]);
        $this->assertSame(0, $pregnant->energy['adjustment'], 'no weight-loss deficit');
        $this->assertSame(27.0, $pregnant->get('iron_mg')->value);
        $this->assertCount(1, $pregnant->advice);

        // Step 7, batch 2: no carbohydrate figure for gestational diabetes; no weight loss in
        // pregnancy, by its reference (NICE NG247 1.2.15), said with its reason.
        $gdm = $this->calculate(['sex' => 'female', 'conditions' => ['gestational_diabetes']]);
        $this->assertSame('balanced', $gdm->template['key']);
        $this->assertNull($gdm->get('meal_carbohydrate_g'));
        $this->assertSame(['adjustment' => 0, 'held' => 'reference'], array_intersect_key($gdm->energy, ['adjustment' => 0, 'held' => 0]));
        $this->assertStringContainsString('أثناء الحمل غير منصوح به', $gdm->energy['held_why']);
        $this->assertSame('reference', $pregnant->energy['held']);

        $this->assertSame(18.0, $this->calculate(['sex' => 'female', 'conditions' => ['anemia']])->get('iron_mg')->value, 'not rounded to 20');
        $this->assertSame(8.0, $this->calculate(['conditions' => ['anemia']])->get('iron_mg')->value);
        $this->assertSame(1200.0, $this->calculate(['conditions' => ['osteoporosis']])->get('calcium_mg')->value);
        $this->assertSame(25, $this->calculate(['sex' => 'female', 'conditions' => ['menopause']])->template['macros']['protein']);
    }

    public function test_a_chosen_template_replaces_the_automatic_one_but_not_the_conditions_limits(): void
    {
        $this->pendingDiabetes();
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
