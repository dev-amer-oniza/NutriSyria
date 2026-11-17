<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Data\Nutrition\MealSuggestion;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use App\Services\Nutrition\Fit\MealFitCalculator;
use App\Services\Nutrition\Fit\MealSuggestions;
use PHPUnit\Framework\TestCase;

/** Step 6: changes tried on a meal and judged again in full, offered only when they help. */
class MealSuggestionsTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = require __DIR__.'/../../config/nutrition_criteria.php';
    }

    private function sheet(array $conditions = [], array $allergens = []): CriteriaSheet
    {
        return (new CriteriaCalculator($this->rules))->calculate([
            'sex' => 'male', 'age' => 30, 'height_cm' => 170, 'weight_kg' => 70,
            'activity' => 'light', 'goal' => 'maintain', 'conditions' => $conditions, 'allergens' => $allergens,
        ]);
    }

    /** A musabbaha serving of 176 g: its fat from tahini, oil on top and chickpeas. */
    private function meal(array $fat, bool $kitchen = true, array $allergens = []): MealFacts
    {
        $lines = [];
        foreach ($fat as [$label, $grams, $amount, $after]) {
            $lines[] = ['label' => $label, 'after' => $after, 'grams' => $grams, 'nutrients' => ['fat_g' => $amount, 'energy_kcal' => $amount * 9]];
        }
        $total = array_sum(array_column($fat, 2));

        return new MealFacts(
            id: 4, name: 'مسبّحة', brand: 'المطبخ السوري', description: null, servingGrams: 176,
            nutrients: ['energy_kcal' => 200 + $total * 9, 'carbohydrate_g' => 40, 'protein_g' => 16, 'fat_g' => $total, 'fiber_g' => 6, 'sodium_mg' => 300, 'fat_sat_g' => 3],
            verification: 'calculated', image: null, allergens: $allergens, allergensChecked: true, platform: $kitchen, lines: $lines,
        );
    }

    /** @return array{0: MealFit, 1: list<MealSuggestion>} */
    private function suggest(CriteriaSheet $sheet, MealFacts $meal): array
    {
        $calculator = new MealFitCalculator($this->rules);
        $fit = $calculator->judge($sheet, $meal);

        return [$fit, (new MealSuggestions($calculator))->for($sheet, $meal, $fit)];
    }

    public function test_a_change_is_offered_only_when_the_meal_judged_again_does_better(): void
    {
        $meal = $this->meal([['طحينة', 30, 20.0, false], ['زيت زيتون', 14, 14.0, true], ['حمص', 120, 6.0, false]]);
        [$fit, $suggestions] = $this->suggest($this->sheet(), $meal);

        $this->assertLessThan(4, $fit->level, 'the fat went over');
        $this->assertNotEmpty($suggestions);
        $this->assertLessThanOrEqual(MealSuggestions::MAX, count($suggestions));
        $calculator = new MealFitCalculator($this->rules);
        foreach ($suggestions as $s) {
            $this->assertTrue($s->improved);
            $this->assertGreaterThan($fit->level, $s->level);
            $this->assertSame('fat_g', $s->nutrient);
            // The level said is the level the changed meal really gets.
            $changed = $meal;
            foreach ($s->changes as $c) {
                $line = array_values(array_filter($meal->lines, fn ($l) => $l['label'] === $c['ingredient']))[0];
                $changed = $changed->without($line, $c['keep']);
            }
            $this->assertSame($s->level, $calculator->judge($this->sheet(), $changed)->level);
            $this->assertEqualsWithDelta($changed->amount('fat_g'), $s->amount, 0.001);
        }
    }

    public function test_the_smallest_cut_that_helps_is_the_one_offered(): void
    {
        // Without the oil on top it is already within: no tahini cut is needed first.
        $meal = $this->meal([['طحينة', 30, 14.0, false], ['زيت زيتون', 14, 14.0, true], ['حمص', 120, 3.0, false]]);
        [, $suggestions] = $this->suggest($this->sheet(), $meal);

        $this->assertSame('بلا زيت زيتون على الوجه', $suggestions[0]->text(false));
        $this->assertSame(['c' => [['i' => 'زيت زيتون', 'a' => 1, 'k' => 0.0]]], $suggestions[0]->change());
        foreach ($suggestions as $s) {
            foreach ($s->changes as $c) {
                $this->assertContains($c['keep'], [0.0, ...MealSuggestions::KEEP]);
            }
        }
    }

    public function test_a_restaurant_is_told_only_what_can_be_left_out_and_no_amounts(): void
    {
        $meal = $this->meal([['طحينة', 30, 14.0, false], ['سمنة', 14, 14.0, true], ['حمص', 120, 3.0, false]], kitchen: false);
        [, $suggestions] = $this->suggest($this->sheet(), $meal);

        $this->assertCount(1, $suggestions);
        $this->assertSame('اطلبها بلا سمنة على الوجه', $suggestions[0]->text(true));
        $this->assertNull($suggestions[0]->amount);
        $this->assertNull($suggestions[0]->nutrient);
    }

    public function test_a_limit_not_verified_yet_gets_a_change_to_come_within_it_without_a_verdict(): void
    {
        // Fat intolerance's 15 g a meal waits for its references: the verdict stays, but the
        // oil and some tahini together bring the plate within it (decision 2a).
        $meal = $this->meal([['طحينة', 30, 14.0, false], ['زيت زيتون', 7, 7.0, true], ['حمص', 120, 3.3, false]]);
        [$fit, $suggestions] = $this->suggest($this->sheet(['fat_intolerance']), $meal);

        $this->assertSame(['fat_g'], $fit->infoCauses);
        $this->assertNotEmpty($suggestions);
        $this->assertFalse($suggestions[0]->improved);
        $this->assertSame($fit->level, $suggestions[0]->level);
        $this->assertSame('عدم تحمّل الدسم', $suggestions[0]->within);
        $this->assertCount(2, $suggestions[0]->changes, 'no one change is enough here');
        $this->assertLessThanOrEqual(15, $suggestions[0]->amount);
    }

    public function test_nothing_is_offered_for_an_allergen_or_a_meal_already_within(): void
    {
        $fat = [['طحينة', 30, 20.0, false], ['زيت زيتون', 14, 14.0, true]];
        $this->assertSame([], $this->suggest($this->sheet(allergens: ['sesame']), $this->meal($fat, allergens: ['sesame']))[1]);
        $this->assertSame([], $this->suggest($this->sheet(), $this->meal([['طحينة', 10, 5.0, false]]))[1]);
    }
}
