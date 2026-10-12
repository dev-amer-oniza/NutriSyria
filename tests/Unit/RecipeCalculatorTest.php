<?php

namespace Tests\Unit;

use App\Services\Nutrition\RecipeCalculator;
use PHPUnit\Framework\TestCase;

class RecipeCalculatorTest extends TestCase
{
    private array $nutrientDefs;

    protected function setUp(): void
    {
        $this->nutrientDefs = [
            ['id' => 1, 'code' => 'energy_kcal', 'name_ar' => 'الطاقة', 'unit' => 'kcal', 'is_core' => true, 'display_order' => 10],
            ['id' => 2, 'code' => 'protein_g', 'name_ar' => 'البروتين', 'unit' => 'g', 'is_core' => true, 'display_order' => 40],
            ['id' => 3, 'code' => 'vitamin_k_ug', 'name_ar' => 'فيتامين K', 'unit' => 'ug', 'is_core' => false, 'display_order' => 310],
        ];
    }

    private function calc(): RecipeCalculator
    {
        return new RecipeCalculator();
    }

    public function test_simple_two_ingredient_sum(): void
    {
        // 200g of something with 100 kcal/8g protein per 100g, + 50g of something with 50 kcal/2g protein per 100g
        $ingredients = [
            ['label' => 'أرز', 'grams' => 200, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 100, 'unit' => 'kcal'], 2 => ['amount' => 8, 'unit' => 'g']]],
            ['label' => 'بصل', 'grams' => 50, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 50, 'unit' => 'kcal'], 2 => ['amount' => 2, 'unit' => 'g']]],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 250, 220, 2);

        $this->assertEqualsWithDelta(225.0, $r->totals[1]['amount'], 0.001); // 200 + 100
        $this->assertEqualsWithDelta(17.0, $r->totals[2]['amount'], 0.001);  // 16 + 1
        $this->assertSame(250.0, $r->gramsBeforeCooking);
        $this->assertSame(250.0, $r->gramsTotal);
    }

    public function test_missing_nutrient_is_null_not_zero_and_names_the_food(): void
    {
        $ingredients = [
            ['label' => 'أرز', 'grams' => 100, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 100, 'unit' => 'kcal']]], // no vitamin K at all
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 100, 90, 1);

        $this->assertNull($r->totals[3]['amount']);
        $this->assertSame(['أرز'], $r->totals[3]['missing_from']);
        // energy_kcal still computes fine despite vitamin K being absent
        $this->assertEqualsWithDelta(100.0, $r->totals[1]['amount'], 0.001);
    }

    public function test_partial_missing_sums_only_the_ingredients_that_have_it(): void
    {
        $ingredients = [
            ['label' => 'أرز', 'grams' => 100, 'is_added_after_cooking' => false,
                'nutrients' => [3 => ['amount' => 10, 'unit' => 'ug']]],
            ['label' => 'بصل', 'grams' => 100, 'is_added_after_cooking' => false,
                'nutrients' => []], // missing vitamin K
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 200, 180, 1);

        $this->assertEqualsWithDelta(10.0, $r->totals[3]['amount'], 0.001);
        $this->assertSame(['بصل'], $r->totals[3]['missing_from']);
    }

    public function test_added_after_cooking_excluded_from_before_cooking_weight_but_counted_in_totals(): void
    {
        $ingredients = [
            ['label' => 'أرز مطبوخ', 'grams' => 200, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 100, 'unit' => 'kcal']]],
            ['label' => 'زيت للتزيين', 'grams' => 10, 'is_added_after_cooking' => true,
                'nutrients' => [1 => ['amount' => 900, 'unit' => 'kcal']]],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 200, 210, 1);

        $this->assertSame(200.0, $r->gramsBeforeCooking);  // oil not counted here
        $this->assertSame(210.0, $r->gramsTotal);          // but counted in the overall total
        $this->assertEqualsWithDelta(290.0, $r->totals[1]['amount'], 0.001); // 200 + 90 kcal from oil
        $this->assertNull($r->weightWarning); // 200 matches the stated 200g exactly
    }

    public function test_weight_mismatch_warns_when_stated_weight_differs_a_lot(): void
    {
        $ingredients = [
            ['label' => 'أرز', 'grams' => 300, 'is_added_after_cooking' => false, 'nutrients' => []],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 200, 180, 1); // stated 200g, actual 300g

        $this->assertNotNull($r->weightWarning);
        $this->assertStringContainsString('300.0', $r->weightWarning);
        $this->assertStringContainsString('200.0', $r->weightWarning);
    }

    public function test_per_100g_views_scale_correctly(): void
    {
        $ingredients = [
            ['label' => 'أرز', 'grams' => 200, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 100, 'unit' => 'kcal']]],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 200, 150, 2);

        // absolute total = 200 kcal (200g at 100 kcal/100g)
        $this->assertEqualsWithDelta(200.0, $r->totals[1]['amount'], 0.001);
        // per 100g raw ingredient weight: 200 kcal * (100/200) = 100
        $this->assertEqualsWithDelta(100.0, $r->per100gRaw()[1]['amount'], 0.001);
        // per 100g after cooking (150g total, more concentrated): 200 * (100/150) = 133.33
        $this->assertEqualsWithDelta(133.3333, $r->per100gAfterCooking()[1]['amount'], 0.001);
        // per serving (2 servings): 200 / 2 = 100
        $this->assertEqualsWithDelta(100.0, $r->perServing()[1]['amount'], 0.001);
    }

    public function test_unconverted_ingredient_is_reported_left_out_of_totals_and_blocks_saving(): void
    {
        $ingredients = [
            ['label' => 'عدس', 'grams' => 200, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 100, 'unit' => 'kcal']]],
            ['label' => 'زيت زيتون', 'grams' => null, 'problem' => 'لا توجد حصة', 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 884, 'unit' => 'kcal']]],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, null, 180, 1);

        $this->assertSame([['label' => 'زيت زيتون', 'problem' => 'لا توجد حصة']], $r->unconvertedIngredients);
        $this->assertEqualsWithDelta(200.0, $r->totals[1]['amount'], 0.001); // oil not counted as anything
        $this->assertSame(200.0, $r->gramsTotal);
        $this->assertSame([], $r->totals[1]['missing_from']);              // reported separately, not as "missing"
        $this->assertTrue($r->hasWeightAfterCooking());
        $this->assertFalse($r->canSaveOfficialValue());
        $this->assertFalse($r->toArray()['can_save_official_value']);
        $this->assertCount(1, $r->toArray()['unconverted_ingredients']);
    }

    public function test_can_save_when_every_ingredient_converted_and_after_cooking_weight_given(): void
    {
        $ingredients = [
            ['label' => 'أرز', 'grams' => 100, 'is_added_after_cooking' => false, 'nutrients' => []],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, 100, 90, 1);

        $this->assertSame([], $r->unconvertedIngredients);
        $this->assertTrue($r->canSaveOfficialValue());
    }

    public function test_borrowed_and_computed_values_are_counted_and_named(): void
    {
        $ingredients = [
            ['label' => 'زبدة', 'grams' => 10, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 717, 'unit' => 'kcal', 'origin' => ['type' => 'borrowed', 'source' => 'USDA SR Legacy']]]],
            ['label' => 'أرز', 'grams' => 100, 'is_added_after_cooking' => false,
                'nutrients' => [1 => ['amount' => 360, 'unit' => 'kcal', 'origin' => ['type' => 'computed']], 2 => ['amount' => 7, 'unit' => 'g']]],
        ];

        $r = $this->calc()->calculate($ingredients, $this->nutrientDefs, null, 100, 1);

        $this->assertEqualsWithDelta(71.7 + 360, $r->totals[1]['amount'], 1e-9);
        $this->assertSame(['زبدة' => 'USDA SR Legacy'], $r->totals[1]['borrowed']);
        $this->assertSame(['أرز'], $r->totals[1]['computed']);
        $this->assertSame([], $r->totals[2]['borrowed']);
        $this->assertSame(2, $r->filledValueCount());
        $this->assertTrue($r->canSaveOfficialValue());   // filled values never block approval
    }

    public function test_cannot_save_official_value_without_after_cooking_weight(): void
    {
        $r = $this->calc()->calculate([], $this->nutrientDefs, 200, null, 1);

        $this->assertFalse($r->canSaveOfficialValue());
        $this->assertSame([], array_filter($r->per100gAfterCooking(), fn ($row) => $row['amount'] !== null));
    }
}
