<?php

namespace Tests\Unit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\MealFacts;
use App\Services\Nutrition\Criteria\CriteriaCalculator;
use App\Services\Nutrition\Fit\MealFitCalculator;
use PHPUnit\Framework\TestCase;

/**
 * The design's example man (2240 kcal, balanced template). One meal = 30% of the day:
 * 672 kcal, 84 g carbohydrate, 33.6 g protein, 22.5 g fat, 7.5 g saturated, 16.8 g sugars,
 * 9.3 g fibre, 600 mg sodium.
 */
class MealFitCalculatorTest extends TestCase
{
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = require __DIR__.'/../../config/nutrition_criteria.php';
    }

    private function sheet(array $overrides = []): CriteriaSheet
    {
        return (new CriteriaCalculator($this->rules))->calculate($overrides + [
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'weight_kg' => 80,
            'activity' => 'moderate', 'goal' => 'lose', 'conditions' => [], 'allergens' => [],
        ]);
    }

    /** A 350 g serving that fits the example man exactly, changed by $nutrients. */
    private function meal(array $nutrients = [], array $allergens = [], bool $checked = true, ?float $serving = 350): MealFacts
    {
        return new MealFacts(
            id: 1, name: 'فتة', brand: 'مطعم', description: null, servingGrams: $serving,
            nutrients: $nutrients + [
                'energy_kcal' => 600, 'carbohydrate_g' => 80, 'protein_g' => 30, 'fat_g' => 20,
                'fat_sat_g' => 5, 'sugars_g' => 10, 'fiber_g' => 10, 'sodium_mg' => 500,
            ],
            verification: 'calculated', image: null, allergens: $allergens, allergensChecked: $checked,
        );
    }

    private function judge(MealFacts $meal, ?CriteriaSheet $sheet = null)
    {
        return (new MealFitCalculator($this->rules))->judge($sheet ?? $this->sheet(), $meal);
    }

    public function test_a_meal_within_every_limit_suits_very_well(): void
    {
        $fit = $this->judge($this->meal());

        $this->assertSame(4, $fit->level);
        $this->assertSame(['grams' => 350, 'kcal' => 600, 'whole' => true], $fit->portion);
        $this->assertSame(['energy' => 27, 'protein' => 27, 'carbs' => 29, 'fat' => 27], $fit->share);
        $this->assertStringContainsString('27% من احتياجك اليومي', $fit->reasons[0]);
        $this->assertSame([], $fit->notes);
        $this->assertSame('المتوازن (ADA)', $fit->template);
    }

    public function test_the_level_follows_the_largest_overshoot(): void
    {
        // Sodium 720 mg = 20% over its 600 mg meal share.
        $fit = $this->judge($this->meal(['sodium_mg' => 720]));
        $this->assertSame(3, $fit->level);
        $this->assertSame('الصوديوم فيها 720 ملغ، وحصة وجبة من احتياجك 600 ملغ، أي أكثر بـ20%.', $fit->reasons[0]);

        // Fat 31.5 g = 40% over 22.5 g.
        $this->assertSame(2, $this->judge($this->meal(['fat_g' => 31.5]))->level);

        // Energy 1075 kcal = 60% over 672.
        $this->assertSame(1, $this->judge($this->meal(['energy_kcal' => 1075]))->level);

        // Exactly 10% over still suits very well.
        $this->assertSame(4, $this->judge($this->meal(['sodium_mg' => 660]))->level);
    }

    public function test_the_suitable_portion_keeps_every_limit(): void
    {
        // Fat 31.5 g over 22.5 g: 71% of 350 g = 250 g (down to tens), 600 × 250/350 = 429 kcal.
        $fit = $this->judge($this->meal(['fat_g' => 31.5]));
        $this->assertSame(['grams' => 250, 'kcal' => 429, 'whole' => false], $fit->portion);

        // Less than 40% of the serving: no portion is offered.
        $this->assertNull($this->judge($this->meal(['sodium_mg' => 2000]))->portion);
    }

    public function test_reasons_are_ordered_by_overshoot_and_capped_at_three(): void
    {
        $fit = $this->judge($this->meal(['sodium_mg' => 690, 'fat_g' => 30, 'sugars_g' => 30, 'fat_sat_g' => 9]));

        $this->assertCount(3, $fit->reasons);
        $this->assertStringStartsWith('السكريات الكلية', $fit->reasons[0]); // 79% over
        $this->assertStringStartsWith('الدهون', $fit->reasons[1]);          // 33% over
    }

    public function test_being_under_a_minimum_or_protein_is_only_a_note(): void
    {
        $fit = $this->judge($this->meal(['fiber_g' => 2, 'protein_g' => 60]));

        $this->assertSame(4, $fit->level); // more protein than the target does not count
        $this->assertCount(1, $fit->notes);
        $this->assertStringStartsWith('الألياف فيها 2 غ، أقل من حصة وجبة', $fit->notes[0]);
    }

    public function test_a_missing_value_is_left_out_and_named(): void
    {
        $meal = new MealFacts(1, 'فتة', 'مطعم', null, 350,
            ['energy_kcal' => 600, 'carbohydrate_g' => 80, 'protein_g' => 30, 'fat_g' => 20], 'calculated', null, [], true);

        $fit = $this->judge($meal);

        $this->assertSame(4, $fit->level);
        $this->assertSame(['لا تتوفر قيمة الدهون المشبعة والسكريات الكلية والصوديوم لهذه الوجبة، فلم تدخل في التقييم.'], $fit->notes);
    }

    public function test_an_allergen_makes_the_meal_unfit_with_no_portion(): void
    {
        $fit = $this->judge($this->meal([], ['gluten', 'sesame']), $this->sheet(['allergens' => ['gluten']]));

        $this->assertSame(1, $fit->level);
        $this->assertNull($fit->portion);
        $this->assertSame('فيها الغلوتين، ولا يناسبك بحسب حالتك أو حساسيتك.', $fit->reasons[0]);
    }

    public function test_celiac_disease_avoids_gluten_through_its_template(): void
    {
        $fit = $this->judge($this->meal([], ['gluten']), $this->sheet(['conditions' => ['celiac']]));

        $this->assertSame(1, $fit->level);
    }

    public function test_unchecked_ingredients_warn_people_with_allergies_only(): void
    {
        $withAllergy = $this->judge($this->meal([], [], checked: false), $this->sheet(['allergens' => ['peanuts']]));
        $this->assertSame(4, $withAllergy->level);
        $this->assertStringStartsWith('لم نتحقق بعد من مسبّبات الحساسية', $withAllergy->reasons[0]);

        $without = $this->judge($this->meal([], [], checked: false));
        $this->assertStringNotContainsString('مسبّبات الحساسية', implode(' ', $without->reasons));
    }

    public function test_a_diabetic_meal_limit_is_judged_per_meal(): void
    {
        // The diabetes template caps one meal at 60 g carbohydrate: 70 g is 17% over.
        $fit = $this->judge($this->meal(['carbohydrate_g' => 70]), $this->sheet(['conditions' => ['diabetes']]));

        $this->assertSame(3, $fit->level);
        $this->assertStringContainsString('حدّك للوجبة 60 غ', implode(' ', $fit->reasons));
    }

    public function test_values_per_100_g_when_the_serving_is_unknown(): void
    {
        $fit = $this->judge($this->meal(['fat_g' => 31.5], serving: null));

        $this->assertSame(['grams' => 70, 'kcal' => 420, 'whole' => false], $fit->portion);
    }
}
