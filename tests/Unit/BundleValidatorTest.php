<?php

namespace Tests\Unit;

use App\Services\Import\ArabicNormalizer;
use App\Services\Import\BundleValidator;
use App\Services\Import\Lookups;
use PHPUnit\Framework\TestCase;

class BundleValidatorTest extends TestCase
{
    private array $cfg;

    protected function setUp(): void
    {
        $this->cfg = [
            'methods' => ['imported', 'estimated', 'measured', 'laboratory', 'calculated'],
            'confidence_levels' => ['calculated', 'reference', 'local_reference', 'measured', 'reviewed', 'verified'],
            'portion_bases' => ['reference', 'usda', 'measured', 'estimated', 'restaurant_measured'],
            'value_qualifiers' => ['trace', 'below_lod'],
            'checks' => [
                'proximate_sum_tolerance_g' => 5,
                'atwater_tolerance_pct' => 20,
                'atwater_floor_kcal' => 10,
                'rounding_tolerance_g' => 0.5,
            ],
        ];
    }

    private function lookups(): Lookups
    {
        $l = new Lookups();
        $l->dataSources = ['usda_fdc_sr_legacy' => 1];
        $l->forms = ['raw' => 1, 'boiled' => 2];
        foreach ([
            'energy_kcal' => ['kcal', true], 'water_g' => ['g', false], 'protein_g' => ['g', true],
            'fat_g' => ['g', true], 'carbohydrate_g' => ['g', true], 'ash_g' => ['g', false],
            'fat_sat_g' => ['g', false], 'sugars_g' => ['g', false], 'fiber_g' => ['g', false],
        ] as $code => [$unit, $core]) {
            $l->nutrients[$code] = ['id' => count($l->nutrients) + 1, 'unit' => $unit, 'is_core' => $core];
        }
        $l->unitAliases = ['cup' => 1, 'clove' => 2];
        $l->rememberFood(10, 'حمص');
        $l->rememberFood(11, 'ثوم');
        $l->rememberFood(12, 'ثوم'); // deliberate duplicate name => ambiguous

        return $l;
    }

    private function rec(int $id, string $ref, array $over = []): array
    {
        return ['id' => $id, 'row' => $id + 1, 'raw' => $over + [
            'food_id' => '', 'food_name_ar' => 'أرز', 'food_name_en' => '', 'category_path' => '',
            'food_form_code' => 'raw', 'data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => $ref, 'notes' => '',
        ]];
    }

    private function val(int $id, string $ref, string $code, string $amount, array $over = []): array
    {
        return ['id' => $id, 'row' => $id + 1, 'raw' => $over + [
            'data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => $ref, 'nutrient_code' => $code,
            'amount_per_100g' => $amount, 'unit' => '', 'method' => 'imported',
        ]];
    }

    private function run_(array $records, array $values = [], array $portions = [], array $existing = []): array
    {
        return (new BundleValidator($this->lookups(), $this->cfg))->validate($records, $values, $portions, $existing);
    }

    private function codes(array $result, int $rowId): array
    {
        return array_column($result['issues'][$rowId] ?? [], 'code');
    }

    public function test_clean_record_is_ready(): void
    {
        $r = $this->run_([$this->rec(1, 'A')], [$this->val(2, 'A', 'protein_g', '8.5', ['unit' => 'g'])]);
        $this->assertSame('valid', $r['rowStatus'][1]);
        $this->assertSame(1, $r['report']['records']['ready']);
        $this->assertSame(0, $r['report']['records']['blocked']);
    }

    public function test_unknown_form_blocks_only_that_record(): void
    {
        $r = $this->run_([$this->rec(1, 'A', ['food_form_code' => 'boiledd']), $this->rec(2, 'B')]);
        $this->assertTrue(in_array('unknown_food_form', $this->codes($r, 1), true));
        $this->assertSame(1, $r['report']['records']['blocked']);
        $this->assertSame(1, $r['report']['records']['ready']);
    }

    public function test_value_error_blocks_its_record_but_not_others(): void
    {
        $r = $this->run_(
            [$this->rec(1, 'A'), $this->rec(2, 'B')],
            [$this->val(3, 'A', 'protein_g', '8', ['unit' => 'mg']), $this->val(4, 'B', 'protein_g', '8')]
        );
        $this->assertTrue(in_array('unit_mismatch', $this->codes($r, 3), true));
        $this->assertTrue(isset($r['blockedKeys']['usda_fdc_sr_legacy|A']));
        $this->assertFalse(isset($r['blockedKeys']['usda_fdc_sr_legacy|B']));
    }

    public function test_more_than_100g_per_100g_is_an_error(): void
    {
        $r = $this->run_([$this->rec(1, 'A')], [$this->val(2, 'A', 'fat_g', '104')]);
        $this->assertTrue(in_array('exceeds_100g', $this->codes($r, 2), true));
    }

    public function test_negative_and_non_numeric_amounts_are_errors(): void
    {
        $r = $this->run_([$this->rec(1, 'A')], [$this->val(2, 'A', 'fat_g', '-1'), $this->val(3, 'A', 'protein_g', 'abc')]);
        $this->assertTrue(in_array('negative_amount', $this->codes($r, 2), true));
        $this->assertTrue(in_array('not_numeric', $this->codes($r, 3), true));
    }

    public function test_proximate_sum_far_from_100_warns_without_blocking(): void
    {
        $vals = [
            $this->val(2, 'A', 'water_g', '12'), $this->val(3, 'A', 'protein_g', '7'), $this->val(4, 'A', 'fat_g', '1'),
            $this->val(5, 'A', 'carbohydrate_g', '60'), $this->val(6, 'A', 'ash_g', '0.5'),
        ];
        $r = $this->run_([$this->rec(1, 'A')], $vals);
        $this->assertTrue(in_array('proximate_sum', $this->codes($r, 1), true));
        $this->assertSame('valid', $r['rowStatus'][1]);
        $this->assertSame(1, $r['report']['records']['ready']);
    }

    public function test_consistent_proximates_and_energy_produce_no_scientific_warning(): void
    {
        $vals = [
            $this->val(2, 'A', 'water_g', '60'), $this->val(3, 'A', 'protein_g', '8'), $this->val(4, 'A', 'fat_g', '2.5'),
            $this->val(5, 'A', 'carbohydrate_g', '27'), $this->val(6, 'A', 'ash_g', '0.5'), $this->val(7, 'A', 'energy_kcal', '160'),
        ];
        $codes = $this->codes($this->run_([$this->rec(1, 'A')], $vals), 1);
        $this->assertFalse(in_array('proximate_sum', $codes, true));
        $this->assertFalse(in_array('energy_mismatch', $codes, true));
    }

    public function test_energy_that_disagrees_with_macros_warns(): void
    {
        $vals = [
            $this->val(2, 'A', 'protein_g', '8'), $this->val(3, 'A', 'fat_g', '2.5'),
            $this->val(4, 'A', 'carbohydrate_g', '27'), $this->val(5, 'A', 'energy_kcal', '400'),
        ];
        $this->assertTrue(in_array('energy_mismatch', $this->codes($this->run_([$this->rec(1, 'A')], $vals), 1), true));
    }

    public function test_sugars_above_carbohydrate_warns(): void
    {
        $vals = [$this->val(2, 'A', 'carbohydrate_g', '10'), $this->val(3, 'A', 'sugars_g', '14')];
        $this->assertTrue(in_array('sugars_g_exceed_carbohydrate', $this->codes($this->run_([$this->rec(1, 'A')], $vals), 1), true));
    }

    public function test_unmapped_portion_label_is_a_non_blocking_error(): void
    {
        $portions = [
            ['id' => 5, 'row' => 2, 'raw' => ['data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => 'A', 'measure_unit_label' => 'medium', 'amount' => '1', 'gram_weight' => '50', 'basis' => 'usda']],
            ['id' => 6, 'row' => 3, 'raw' => ['data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => 'A', 'measure_unit_label' => 'Cup', 'amount' => '1', 'gram_weight' => '164', 'basis' => 'usda']],
        ];
        $r = $this->run_([$this->rec(1, 'A')], [], $portions);
        $this->assertSame('error', $r['rowStatus'][5]);
        $this->assertSame('valid', $r['rowStatus'][6]);
        $this->assertSame(0, $r['report']['records']['blocked']);
        $this->assertSame(['medium' => 1], $r['report']['unmapped_unit_labels']);
    }

    public function test_new_ambiguous_and_existing_foods(): void
    {
        $r = $this->run_([
            $this->rec(1, 'A', ['food_name_ar' => 'أرز']),      // not in DB => new
            $this->rec(2, 'B', ['food_name_ar' => 'حُمّص']),     // normalizes to an existing food
            $this->rec(3, 'C', ['food_name_ar' => 'ثوم']),       // two foods share it => ambiguous
        ]);
        $this->assertSame(1, $r['report']['records']['new_foods']);
        $this->assertSame('valid', $r['rowStatus'][2]);
        $this->assertTrue(in_array('ambiguous_food', $this->codes($r, 3), true));
    }

    public function test_reimport_pointing_at_a_different_food_is_blocked(): void
    {
        $existing = ['usda_fdc_sr_legacy|A' => ['id' => 99, 'food_id' => 10]];
        $r = $this->run_([$this->rec(1, 'A', ['food_id' => '11'])], [], [], $existing);
        $this->assertTrue(in_array('record_linked_to_other_food', $this->codes($r, 1), true));

        $ok = $this->run_([$this->rec(1, 'A', ['food_id' => '10'])], [], [], $existing);
        $this->assertSame('valid', $ok['rowStatus'][1]);
    }

    public function test_duplicates_and_orphans_are_errors(): void
    {
        $r = $this->run_(
            [$this->rec(1, 'A'), $this->rec(2, 'A')],
            [$this->val(3, 'ZZZ', 'protein_g', '5'), $this->val(4, 'A', 'protein_g', '5'), $this->val(5, 'A', 'protein_g', '6')]
        );
        $this->assertTrue(in_array('duplicate_record', $this->codes($r, 2), true));
        $this->assertTrue(in_array('orphan_value', $this->codes($r, 3), true));
        $this->assertTrue(in_array('duplicate_value', $this->codes($r, 5), true));
    }

    public function test_usda_style_unit_labels_resolve_to_their_base_unit(): void
    {
        $l = $this->lookups();
        $l->unitAliases += ['slice' => 3, 'piece' => 4, 'large' => 4, 'fl oz' => 5, 'head' => 6, 'tbsp' => 7];

        $this->assertSame(1, $l->resolveUnitLabel('Cup, chopped'));
        $this->assertSame(1, $l->resolveUnitLabel('cup (1" cubes)'));
        $this->assertSame(3, $l->resolveUnitLabel('slice, large (1/4" thick)'));
        $this->assertSame(4, $l->resolveUnitLabel('large (3-1/16" dia)'));
        $this->assertSame(5, $l->resolveUnitLabel('fl oz'));
        $this->assertSame(6, $l->resolveUnitLabel('head, large (about 7" dia)'));
        $this->assertSame(7, $l->resolveUnitLabel('tbsp chopped'));
        $this->assertSame(null, $l->resolveUnitLabel('lemon yields'));
        $this->assertTrue($l->isExactUnitLabel('Cup'));
        $this->assertFalse($l->isExactUnitLabel('cup, chopped'));
    }

    public function test_portion_with_unit_plus_description_is_valid(): void
    {
        $portions = [['id' => 5, 'row' => 2, 'raw' => ['data_source_code' => 'usda_fdc_sr_legacy', 'external_ref' => 'A',
            'measure_unit_label' => 'cup, sliced', 'amount' => '1', 'gram_weight' => '110', 'basis' => 'usda']]];
        $r = $this->run_([$this->rec(1, 'A')], [], $portions);
        $this->assertSame('valid', $r['rowStatus'][5]);
    }

    public function test_arabic_normalizer_ignores_diacritics_and_alef_forms(): void
    {
        $this->assertSame(ArabicNormalizer::normalize('حمص'), ArabicNormalizer::normalize('حُمُّص'));
        $this->assertSame(ArabicNormalizer::normalize('ارز'), ArabicNormalizer::normalize('أرز'));
        $this->assertSame('طحينه', ArabicNormalizer::normalize('طحينة'));
    }
}
