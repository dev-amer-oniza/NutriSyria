<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Enums\CriterionSource;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The new calculation (the owner's decisions, Nov 2026), on cases worked out by hand:
 * TDEE = BMR (Mifflin) × the day's factor (FAO 2004) + the exercise's extra (Compendium 2024),
 * then the goal (−500 / 0 / +300) within the minimum, then protein by kg, fat 30%, carbs the rest.
 */
class CriteriaCalculatorTest extends TestCase
{
    /** Rules laid over the config: a fixture for the engine, not the rules themselves. */
    private array $patch = [];

    /** Amer: man, 24, 170 cm, 70 kg, home most of the day, a normal walk twice a week for an hour. */
    private const AMER = [
        'sex' => 'male', 'age' => 24, 'height_cm' => 170, 'weight_kg' => 70,
        'daily_life' => 'home', 'work_days' => null, 'exercises' => [['type' => 'walk', 'times' => 2, 'minutes' => 60]],
    ];

    /** The student: man, 24, 170 cm, 68 kg, university 3 days, weights 3 × 60 and cardio 3 × 15. */
    private const STUDENT = [
        'sex' => 'male', 'age' => 24, 'height_cm' => 170, 'weight_kg' => 68, 'daily_life' => 'seated', 'work_days' => 3,
        'exercises' => [['type' => 'weights', 'times' => 3, 'minutes' => 60], ['type' => 'cardio', 'times' => 3, 'minutes' => 15]],
    ];

    private function calculate(array $profile, string $goal = 'maintain', array $more = []): CriteriaSheet
    {
        $rules = array_replace_recursive(require __DIR__.'/../../config/nutrition_criteria.php', $this->patch);

        return (new CriteriaCalculator($rules))->calculate($more + ['goal' => $goal, 'allergens' => []] + $profile);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} kcal, protein, fat, carbohydrate */
    private function macros(CriteriaSheet $sheet): array
    {
        return array_map(fn (string $k) => $sheet->get($k)->value, ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g']);
    }

    public function test_amer_home_and_a_walk_twice_a_week(): void
    {
        $sheet = $this->calculate(self::AMER);

        // BMR 10×70 + 6.25×170 − 5×24 + 5 = 1647.5; × 1.40 = 2306.5;
        // walking: 3.0 × 70 − 1.4 × 1647.5 ÷ 24 = 113.9 an hour, × 2 hours ÷ 7 = 32.5 a day; 2339.0.
        $this->assertSame(
            ['bmr' => 1648, 'factor' => 1.4, 'base' => 2307, 'exercise' => 33, 'tdee' => 2339, 'pal' => 1.42, 'unusual' => false,
                'adjustment' => 0, 'floored' => false, 'held' => false],
            $sheet->energy,
        );
        // Protein 1.2 × 70 = 84 g; fat 30% = 78 g; carbohydrate the rest = 325 g (56%).
        $this->assertSame([2339.0, 84.0, 78.0, 325.0], $this->macros($sheet));
        $this->assertSame(['balanced', CriterionSource::Default], [$sheet->template['key'], $sheet->template['source']]);
        $this->assertSame(['carbs' => 56, 'protein' => 14, 'fat' => 30], $sheet->template['macros']);
    }

    public function test_amer_losing_and_gaining(): void
    {
        // 2339 − 500: protein 1.6 × 70 = 112 g, fat 30% = 61 g, carbohydrate 210 g (46%, over 45%).
        $lose = $this->calculate(self::AMER, 'lose');
        $this->assertSame([1839.0, 112.0, 61.0, 210.0], $this->macros($lose));
        $this->assertSame('weight_loss', $lose->template['key']);

        // + 300 (our choice): 2639, protein 112 g, fat 88 g, carbohydrate 350 g.
        $gain = $this->calculate(self::AMER, 'gain');
        $this->assertSame([2639.0, 112.0, 88.0, 350.0], $this->macros($gain));
        $this->assertSame('gain', $gain->template['key']);
    }

    public function test_the_student_university_three_days_and_the_gym(): void
    {
        $sheet = $this->calculate(self::STUDENT);

        // BMR 1627.5; the day (3 × 1.53 + 4 × 1.40) ÷ 7 = 1.456 → 2369.2;
        // weights (3.5 × 68 − 94.9) × 3 h ÷ 7 = 61.3, cardio (5.0 × 68 − 94.9) × 0.75 h ÷ 7 = 26.3; 2456.7.
        $this->assertSame([1628, 1.456, 2369, 88, 2457, 1.51], [
            $sheet->energy['bmr'], $sheet->energy['factor'], $sheet->energy['base'], $sheet->energy['exercise'], $sheet->energy['tdee'], $sheet->energy['pal'],
        ]);
        // Training 3 days a week: the sport template, with the goal's protein.
        $this->assertSame('sport', $sheet->template['key']);
        $this->assertSame([2457.0, 82.0, 82.0, 348.0], $this->macros($sheet));
        $this->assertSame([1957.0, 109.0, 65.0, 234.0], $this->macros($this->calculate(self::STUDENT, 'lose')));
        $this->assertSame([2757.0, 109.0, 92.0, 374.0], $this->macros($this->calculate(self::STUDENT, 'gain')));
    }

    public function test_seated_every_day_with_weights_four_times(): void
    {
        // Ahmad: man, 28, 178 cm, 80 kg. BMR 1777.5 × 1.53 = 2719.6;
        // weights (3.5 × 80 − 1.4 × 1777.5 ÷ 24) × 4 h ÷ 7 = 100.7; 2820.3.
        $sheet = $this->calculate([
            'sex' => 'male', 'age' => 28, 'height_cm' => 178, 'weight_kg' => 80, 'daily_life' => 'seated', 'work_days' => 7,
            'exercises' => [['type' => 'weights', 'times' => 4, 'minutes' => 60]],
        ]);

        $this->assertSame([1778, 1.53, 2720, 101, 2820, 1.59], [
            $sheet->energy['bmr'], $sheet->energy['factor'], $sheet->energy['base'], $sheet->energy['exercise'], $sheet->energy['tdee'], $sheet->energy['pal'],
        ]);
        $this->assertSame('sport', $sheet->template['key']);
    }

    public function test_a_woman_standing_at_work_with_a_brisk_walk(): void
    {
        // Lina: woman, 35, 162 cm, 68 kg. BMR 10×68 + 6.25×162 − 5×35 − 161 = 1356.5; × 1.64 = 2224.7;
        // brisk walking (4.8 × 68 − 1.4 × 1356.5 ÷ 24) × 2 h ÷ 7 = 70.6; 2295.3.
        $sheet = $this->calculate([
            'sex' => 'female', 'age' => 35, 'height_cm' => 162, 'weight_kg' => 68, 'daily_life' => 'standing', 'work_days' => 7,
            'exercises' => [['type' => 'brisk_walk', 'times' => 2, 'minutes' => 60]],
        ]);

        $this->assertSame([1357, 2225, 71, 2295], [$sheet->energy['bmr'], $sheet->energy['base'], $sheet->energy['exercise'], $sheet->energy['tdee']]);
        $this->assertSame(46.0, $sheet->get('protein_min_g')->value, 'the RDA for a woman');
    }

    public function test_the_deficit_stops_at_the_minimum_and_fat_comes_down_for_the_carbohydrate(): void
    {
        // Woman, 45, 155 cm, 50 kg, at home: BMR 1082.75 × 1.4 = 1515.9; − 500 = 1016 → 1200.
        $sheet = $this->calculate(['sex' => 'female', 'age' => 45, 'height_cm' => 155, 'weight_kg' => 50, 'daily_life' => 'home'], 'lose');

        $this->assertSame([1516, -316, true, false], [$sheet->energy['tdee'], $sheet->energy['adjustment'], $sheet->energy['floored'], $sheet->energy['held']]);
        // Protein 1.6 × 50 = 80 g (27%); fat 30% would leave carbohydrate at 43%, so 25%:
        // 1200 × 25% ÷ 9 = 33 g; carbohydrate (1200 − 320 − 300) ÷ 4 = 145 g (48%).
        $this->assertSame([1200.0, 80.0, 33.0, 145.0], $this->macros($sheet));
        $this->assertSame(25, $sheet->template['macros']['fat']);
    }

    public function test_no_deficit_when_the_day_is_already_at_the_minimum(): void
    {
        // Woman, 70, 150 cm, 42 kg, at home: BMR 846.5 × 1.4 = 1185 kcal, under 1200: no deficit at all.
        $sheet = $this->calculate(['sex' => 'female', 'age' => 70, 'height_cm' => 150, 'weight_kg' => 42, 'daily_life' => 'home'], 'lose');

        $this->assertSame([1185, 0, false, true], [$sheet->energy['tdee'], $sheet->energy['adjustment'], $sheet->energy['floored'], $sheet->energy['held']]);
        $this->assertSame(1185.0, $sheet->get('energy_kcal')->value);
    }

    public function test_protein_never_passes_35_percent_of_the_calories(): void
    {
        // Woman, 45, 150 cm, 80 kg, at home: BMR 1351.5 × 1.4 = 1892; − 500 = 1392.
        // 1.6 × 80 = 128 g would be 37%: held at 1392 × 35% ÷ 4 = 122 g.
        $sheet = $this->calculate(['sex' => 'female', 'age' => 45, 'height_cm' => 150, 'weight_kg' => 80, 'daily_life' => 'home'], 'lose');

        $this->assertSame([1392.0, 122.0], [$sheet->get('energy_kcal')->value, $sheet->get('protein_g')->value]);
    }

    public function test_an_unusual_day_asks_to_check_the_answers(): void
    {
        // Physical work all week and running two hours a day: 4075 kcal, 2.47 × BMR, past FAO's 2.40.
        $sheet = $this->calculate(['daily_life' => 'physical', 'work_days' => 7, 'exercises' => [['type' => 'run', 'times' => 7, 'minutes' => 120]]] + self::AMER);

        $this->assertTrue($sheet->energy['unusual']);
        $this->assertFalse($this->calculate(self::AMER)->energy['unusual']);
    }

    public function test_the_other_criteria_follow_the_calories(): void
    {
        $sheet = $this->calculate(self::AMER);

        // 2339 × 10% ÷ 9 (saturated), × 10% ÷ 4 (sugars), × 14 ÷ 1000 (fiber); the RDA for a man, 56 g.
        $this->assertSame(
            ['fat_sat_g' => 26.0, 'sugars_g' => 58.0, 'fiber_g' => 33.0, 'sodium_mg' => 2000.0, 'protein_min_g' => 56.0],
            array_map(fn (string $k) => $sheet->get($k)->value, array_combine($keys = ['fat_sat_g', 'sugars_g', 'fiber_g', 'sodium_mg', 'protein_min_g'], $keys)),
        );
        $this->assertEqualsWithDelta(701.7, $sheet->perMeal($sheet->get('energy_kcal')), 0.001); // 30% of 2339
        foreach (['meal_fat_g', 'iron_mg', 'calcium_mg', 'meal_carbohydrate_g'] as $key) {
            $this->assertNull($sheet->get($key), $key);
        }
    }

    public function test_a_nutritionists_template_brings_its_protein_and_its_rules(): void
    {
        $this->patch = ['templates' => ['low_protein' => [
            'label' => 'تجربة', 'group' => 'general', 'description' => '', 'protein_per_kg' => 0.8,
            'rules' => ['protein_g' => ['type' => 'per_kg', 'value' => 0.6, 'bound' => 'max']],
        ]]];
        $sheet = $this->calculate(self::STUDENT, 'maintain', ['template' => 'low_protein', 'template_source' => CriterionSource::Nutritionist]);

        // The nutritionist's choice wins over the sport template.
        $this->assertSame(['low_protein', CriterionSource::Nutritionist], [$sheet->template['key'], $sheet->template['source']]);
        $this->assertSame(['max', 41.0], [$sheet->get('protein_g')->bound, $sheet->get('protein_g')->value]); // 0.6 × 68
        $this->assertNull($sheet->get('protein_min_g'), 'no RDA floor (56 g) above a protein ceiling (41 g)');
    }

    public function test_an_unknown_template_falls_back_to_the_answers(): void
    {
        $this->assertSame('weight_loss', $this->calculate(self::AMER, 'lose', ['template' => 'gone', 'template_source' => CriterionSource::Nutritionist])->template['key']);
    }

    public function test_every_template_and_goal_has_its_protein(): void
    {
        $rules = require __DIR__.'/../../config/nutrition_criteria.php';

        foreach ($rules['goal_templates'] as $goal => $key) {
            $this->assertArrayHasKey($key, $rules['templates'], $goal);
        }
        foreach ($rules['templates'] as $key => $template) {
            foreach ((array) $template['protein_per_kg'] as $perKg) {
                $this->assertGreaterThanOrEqual(1.2, $perKg, $key);
                $this->assertLessThanOrEqual(1.6, $perKg, $key);
            }
        }
        $this->assertArrayHasKey($rules['default_template'], $rules['templates']);
    }
}
