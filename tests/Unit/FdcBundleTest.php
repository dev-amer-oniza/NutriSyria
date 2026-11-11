<?php

namespace Tests\Unit;

use App\Services\Usda\FdcBundle;
use PHPUnit\Framework\TestCase;

/** One USDA food from the API becomes the same bundle rows the matching tool builds. */
class FdcBundleTest extends TestCase
{
    private const MAP = [
        ['code' => '1008', 'nutrient_code' => 'energy_kcal', 'unit' => 'kcal', 'factor' => 1.0, 'priority' => 100],
        ['code' => '2047', 'nutrient_code' => 'energy_kcal', 'unit' => 'kcal', 'factor' => 1.0, 'priority' => 120],
        ['code' => '1003', 'nutrient_code' => 'protein_g', 'unit' => 'g', 'factor' => 1.0, 'priority' => 100],
        ['code' => '1162', 'nutrient_code' => 'vitamin_c_mg', 'unit' => 'mg', 'factor' => 1.0, 'priority' => 100],
        ['code' => '1079', 'nutrient_code' => 'fiber_mg', 'unit' => 'mg', 'factor' => 1000.0, 'priority' => 100],
    ];

    private function food(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/fdc/food-173757.json'), true);
    }

    public function test_each_nutrient_takes_its_first_source_code_and_bad_amounts_are_left_out(): void
    {
        $values = collect(FdcBundle::values($this->food(), self::MAP))->keyBy('nutrient_code');

        $this->assertSame(['energy_kcal', 'protein_g', 'fiber_mg'], $values->keys()->all());   // no header row, no negative vitamin C, nothing unmapped
        $this->assertSame(164.0, $values['energy_kcal']['amount']);                              // 1008 before the Atwater figure
        $this->assertSame(7600.0, $values['fiber_mg']['amount']);                                // the map's factor
        $this->assertSame(['4', '8.1', '9.4', 'A: Analytical'], [$values['protein_g']['sample_count'], $values['protein_g']['min'], $values['protein_g']['max'], $values['protein_g']['derivation']]);
    }

    public function test_the_atwater_energy_stands_in_when_the_reported_one_is_missing(): void
    {
        $food = $this->food();
        $food['foodNutrients'] = array_values(array_filter($food['foodNutrients'], fn ($n) => $n['nutrient']['id'] !== 1008));

        $this->assertSame(170.0, collect(FdcBundle::values($food, self::MAP))->firstWhere('nutrient_code', 'energy_kcal')['amount']);
    }

    public function test_a_portion_is_named_by_its_unit_or_else_its_modifier(): void
    {
        $portions = FdcBundle::portions($this->food());

        $this->assertSame(['cup', 'tbsp', 'cup'], array_column($portions, 'label'));           // the one without a weight is dropped
        $this->assertSame([164.0, 10.2, 200.0], array_column($portions, 'grams'));
        $this->assertSame([0.5, 'mashed'], [$portions[2]['amount'], $portions[2]['modifier']]);
    }

    public function test_the_sheets_carry_the_source_the_reference_and_the_note(): void
    {
        $sheets = FdcBundle::sheets($this->food(), self::MAP, ['food_name_ar' => 'حمص', 'food_form_code' => 'boiled', 'note' => 'مقاربة: حمص بلدي']);

        $this->assertSame([
            'food_id' => '', 'food_name_ar' => 'حمص', 'food_name_en' => '', 'category_path' => '', 'food_form_code' => 'boiled',
            'data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => '173757',
            'notes' => 'مقاربة: حمص بلدي | FDC: Chickpeas (garbanzo beans, bengal gram), mature seeds, cooked, boiled, without salt (published 2019-04-01)',
        ], $sheets['records'][0]);
        $this->assertSame(['usda_fdc_sr_legacy', '173757', 'energy_kcal', '164', 'kcal', 'imported'], array_slice(array_values($sheets['nutrient_values'][0]), 0, 6));
        $this->assertSame(['usda_fdc_sr_legacy', '173757', 'cup', '1', '164', '', 'cup', 'usda'], array_values($sheets['portions'][0]));
    }

    public function test_other_releases_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FdcBundle::sheets(['fdcId' => 1, 'dataType' => 'Branded'], self::MAP, ['food_form_code' => 'raw']);
    }
}
