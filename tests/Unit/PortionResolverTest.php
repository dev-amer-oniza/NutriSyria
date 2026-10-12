<?php

namespace Tests\Unit;

use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\PortionResolver;
use PHPUnit\Framework\TestCase;

class PortionResolverTest extends TestCase
{
    private const RAW = 1;
    private const BOILED = 8;

    private const UNITS = [
        'g' => ['id' => 1, 'code' => 'g', 'name_ar' => 'غرام', 'dimension' => 'mass', 'base_factor' => 1.0],
        'kg' => ['id' => 2, 'code' => 'kg', 'name_ar' => 'كيلوغرام', 'dimension' => 'mass', 'base_factor' => 1000.0],
        'mg' => ['id' => 3, 'code' => 'mg', 'name_ar' => 'ملغ', 'dimension' => 'mass', 'base_factor' => 0.001],
        'oz' => ['id' => 4, 'code' => 'oz', 'name_ar' => 'أونصة', 'dimension' => 'mass', 'base_factor' => 28.349523],
        'lb' => ['id' => 5, 'code' => 'lb', 'name_ar' => 'باوند', 'dimension' => 'mass', 'base_factor' => 453.59237],
        'ml' => ['id' => 6, 'code' => 'ml', 'name_ar' => 'مل', 'dimension' => 'volume', 'base_factor' => 1.0],
        'l' => ['id' => 7, 'code' => 'l', 'name_ar' => 'لتر', 'dimension' => 'volume', 'base_factor' => 1000.0],
        'cup' => ['id' => 10, 'code' => 'cup', 'name_ar' => 'كوب', 'dimension' => 'household', 'base_factor' => null],
        'piece' => ['id' => 13, 'code' => 'piece', 'name_ar' => 'حبة', 'dimension' => 'count', 'base_factor' => null],
        'fl_oz' => ['id' => 16, 'code' => 'fl_oz', 'name_ar' => 'أونصة سائلة', 'dimension' => 'volume', 'base_factor' => 29.57353],
    ];

    private const LABELS = [
        'cup' => ['cup', 'كوب'],
        'piece' => ['piece', 'حبة', 'each', 'small', 'medium', 'large'],
        'ml' => ['ml', 'مل', 'millilitre'],
        'fl_oz' => ['fl_oz', 'fl oz', 'fluid ounce', 'أونصة سائلة'],
    ];

    private int $nextId = 100;

    private function resolver(): PortionResolver
    {
        return new PortionResolver();
    }

    /** Boiled chickpeas, source record #17. */
    private function record(array $over = []): array
    {
        return $over + ['id' => 17, 'food_form_id' => self::BOILED, 'food_name_ar' => 'حمص', 'form_name_ar' => 'مسلوق'];
    }

    private function portion(string $unit, float $grams, array $over = []): array
    {
        $u = self::UNITS[$unit];

        return $over + [
            'id' => $this->nextId++,
            'measure_unit_id' => $u['id'],
            'unit_name_ar' => $u['name_ar'],
            'unit_dimension' => $u['dimension'],
            'unit_base_factor' => $u['base_factor'],
            'unit_labels' => self::LABELS[$unit] ?? [$u['code'], $u['name_ar']],
            'amount' => 1.0,
            'gram_weight' => $grams,
            'description' => null,
            'food_source_record_id' => 17,
            'food_form_id' => self::BOILED,
            'form_name_ar' => 'مسلوق',
            'record_status' => 'active',
            'is_default' => false,
            'confidence_level' => 'reference',
        ];
    }

    private function convert(string $unit, float $amount, array $portions, ?array $record = null): GramsConversion
    {
        return $this->resolver()->resolve(self::UNITS[$unit], $record ?? $this->record(), $portions, $amount);
    }

    // ------------------------------------------------------------------ mass units

    public function test_every_mass_unit_converts_through_its_base_factor_without_a_portion(): void
    {
        $this->assertEqualsWithDelta(250.0, $this->convert('g', 250, [])->grams, 1e-9);
        $this->assertEqualsWithDelta(1500.0, $this->convert('kg', 1.5, [])->grams, 1e-9);
        $this->assertEqualsWithDelta(0.5, $this->convert('mg', 500, [])->grams, 1e-9);
        $this->assertEqualsWithDelta(56.699046, $this->convert('oz', 2, [])->grams, 1e-6);
        $this->assertEqualsWithDelta(226.796185, $this->convert('lb', 0.5, [])->grams, 1e-6);
        $this->assertSame(GramsConversion::METHOD_MASS, $this->convert('oz', 2, [])->method);
    }

    public function test_mass_units_do_not_need_an_existing_record(): void
    {
        $c = $this->resolver()->resolve(self::UNITS['oz'], null, [], 1);

        $this->assertTrue($c->ok());
        $this->assertEqualsWithDelta(28.349523, $c->grams, 1e-6);
    }

    public function test_g_and_kg_still_work_for_units_created_without_dimension(): void
    {
        $legacyKg = ['id' => 2, 'code' => 'kg', 'name_ar' => 'كيلوغرام', 'dimension' => null, 'base_factor' => null];

        $this->assertEqualsWithDelta(2000.0, $this->resolver()->resolve($legacyKg, null, [], 2)->grams, 1e-9);
    }

    // ------------------------------------------------------------------ the food form

    public function test_a_cup_of_boiled_chickpeas_uses_the_boiled_portion_not_the_raw_one(): void
    {
        $portions = [
            // raw record (#16) saved first, which is what the old first() picked for every form
            $this->portion('cup', 200, ['food_source_record_id' => 16, 'food_form_id' => self::RAW, 'form_name_ar' => 'نيء']),
            $this->portion('cup', 164),
        ];

        $boiled = $this->convert('cup', 2, $portions);
        $raw = $this->convert('cup', 2, $portions, $this->record(['id' => 16, 'food_form_id' => self::RAW, 'form_name_ar' => 'نيء']));

        $this->assertEqualsWithDelta(328.0, $boiled->grams, 1e-9);
        $this->assertEqualsWithDelta(400.0, $raw->grams, 1e-9);
    }

    public function test_a_same_form_portion_from_another_source_is_used(): void
    {
        $other = $this->portion('cup', 164, ['food_source_record_id' => 99]);

        $this->assertEqualsWithDelta(164.0, $this->convert('cup', 1, [$other])->grams, 1e-9);
    }

    public function test_a_portion_of_another_form_is_never_used(): void
    {
        $raw = $this->portion('cup', 200, ['food_source_record_id' => 16, 'food_form_id' => self::RAW, 'form_name_ar' => 'نيء']);

        $c = $this->convert('cup', 1, [$raw]);

        $this->assertNull($c->grams);
        $this->assertSame(GramsConversion::OTHER_FORM_ONLY, $c->status);
        $this->assertStringContainsString('نيء', $c->message);
        $this->assertStringContainsString('حمص (مسلوق)', $c->message);
    }

    public function test_a_form_less_portion_of_the_food_is_used_as_a_fallback(): void
    {
        $generic = $this->portion('piece', 6, ['food_source_record_id' => null, 'food_form_id' => null, 'form_name_ar' => null]);

        $this->assertEqualsWithDelta(18.0, $this->convert('piece', 3, [$generic])->grams, 1e-9);
    }

    public function test_the_own_record_portion_beats_a_same_form_portion_of_another_source(): void
    {
        $portions = [
            $this->portion('cup', 170, ['food_source_record_id' => 99]),
            $this->portion('cup', 164),
        ];

        $this->assertEqualsWithDelta(164.0, $this->convert('cup', 1, $portions)->grams, 1e-9);
    }

    public function test_a_record_without_form_matches_only_form_less_portions(): void
    {
        $portions = [
            $this->portion('cup', 200, ['food_source_record_id' => 16, 'food_form_id' => self::RAW]),
            $this->portion('cup', 150, ['food_source_record_id' => null, 'food_form_id' => null]),
        ];

        $c = $this->convert('cup', 1, $portions, $this->record(['id' => 50, 'food_form_id' => null, 'form_name_ar' => null]));

        $this->assertEqualsWithDelta(150.0, $c->grams, 1e-9);
    }

    // ------------------------------------------------------------------ choosing among several portions

    public function test_portion_amount_is_respected(): void
    {
        $half = $this->portion('cup', 82, ['amount' => 0.5]);

        $this->assertEqualsWithDelta(328.0, $this->convert('cup', 2, [$half])->grams, 1e-9);
    }

    public function test_a_plain_portion_wins_over_described_variants(): void
    {
        $portions = [
            $this->portion('cup', 246, ['description' => 'cup, mashed']),
            $this->portion('cup', 164, ['description' => '1 cup']),
        ];

        $c = $this->convert('cup', 1, $portions);

        $this->assertEqualsWithDelta(164.0, $c->grams, 1e-9);
        $this->assertSame($portions[1]['id'], $c->portionId);
    }

    public function test_arabic_plain_description_is_recognised(): void
    {
        $portions = [
            $this->portion('cup', 246, ['description' => 'كوب مهروس']),
            $this->portion('cup', 164, ['description' => '١ كوب']),
        ];

        $this->assertEqualsWithDelta(164.0, $this->convert('cup', 1, $portions)->grams, 1e-9);
    }

    public function test_only_described_variants_with_different_weights_are_ambiguous(): void
    {
        $portions = [
            $this->portion('cup', 152, ['description' => 'cup, halves']),
            $this->portion('cup', 232, ['description' => 'cup, pureed']),
            $this->portion('cup', 144, ['description' => 'cup, whole']),
        ];

        $c = $this->convert('cup', 1, $portions);

        $this->assertNull($c->grams);
        $this->assertSame(GramsConversion::AMBIGUOUS_PORTION, $c->status);
        $this->assertStringContainsString('cup, halves', $c->message);
        $this->assertStringContainsString('cup, pureed', $c->message);
        $this->assertStringContainsString('افتراضية', $c->message);
    }

    public function test_several_plain_size_portions_with_different_weights_are_ambiguous(): void
    {
        $portions = [
            $this->portion('piece', 38, ['description' => 'small']),
            $this->portion('piece', 50, ['description' => 'large']),
        ];

        $this->assertSame(GramsConversion::AMBIGUOUS_PORTION, $this->convert('piece', 1, $portions)->status);
    }

    public function test_bare_size_words_are_not_plain_so_sizes_are_never_picked_silently(): void
    {
        // Green pepper, SR Legacy 170427: "small" alone used to win as a "plain" piece (74 g).
        $portions = [
            $this->portion('piece', 74, ['description' => 'small']),
            $this->portion('piece', 119, ['description' => 'medium (approx 2-3/4" long, 2-1/2" dia)']),
            $this->portion('piece', 164, ['description' => 'large (2-1/4 per lb, approx 3-3/4" long, 3" dia)']),
        ];

        $c = $this->convert('piece', 3, $portions);

        $this->assertNull($c->grams);
        $this->assertSame(GramsConversion::AMBIGUOUS_PORTION, $c->status);
        $this->assertStringContainsString('«small»', $c->message);
    }

    public function test_arabic_size_words_are_not_plain_either(): void
    {
        $portions = [
            $this->portion('piece', 38, ['description' => 'صغيرة']),
            $this->portion('piece', 56, ['description' => 'كبيرة']),
        ];

        $this->assertSame(GramsConversion::AMBIGUOUS_PORTION, $this->convert('piece', 1, $portions)->status);
    }

    public function test_a_plain_piece_beats_size_variants(): void
    {
        $portions = [
            $this->portion('piece', 38, ['description' => 'small']),
            $this->portion('piece', 50, ['description' => '1 piece']),
        ];

        $this->assertEqualsWithDelta(100.0, $this->convert('piece', 2, $portions)->grams, 1e-9);
    }

    public function test_the_default_size_settles_the_choice(): void
    {
        $portions = [
            $this->portion('piece', 74, ['description' => 'small']),
            $this->portion('piece', 119, ['description' => 'medium', 'is_default' => true]),
        ];

        $this->assertEqualsWithDelta(357.0, $this->convert('piece', 3, $portions)->grams, 1e-9);
    }

    public function test_size_word_detection(): void
    {
        foreach (['small', 'Large', ' extra large ', 'كبيرة', 'وسط'] as $word) {
            $this->assertTrue(PortionResolver::isSizeWord($word), $word);
        }
        foreach (['cup', 'piece', 'large (3" dia)', 'كوب'] as $word) {
            $this->assertFalse(PortionResolver::isSizeWord($word), $word);
        }
    }

    public function test_portions_with_the_same_weight_are_not_ambiguous(): void
    {
        $portions = [
            $this->portion('cup', 164.0, ['description' => 'cup, whole']),
            $this->portion('cup', 164.5, ['description' => 'cup, drained']),
        ];

        $this->assertEqualsWithDelta(164.0, $this->convert('cup', 1, $portions)->grams, 1.0);
    }

    public function test_the_default_portion_settles_an_ambiguity(): void
    {
        $portions = [
            $this->portion('cup', 152, ['description' => 'cup, halves']),
            $this->portion('cup', 232, ['description' => 'cup, pureed', 'is_default' => true]),
        ];

        $this->assertEqualsWithDelta(232.0, $this->convert('cup', 1, $portions)->grams, 1e-9);
    }

    public function test_a_default_form_less_portion_beats_undecided_portions_of_the_own_record(): void
    {
        $portions = [
            $this->portion('cup', 152, ['description' => 'cup, halves']),
            $this->portion('cup', 232, ['description' => 'cup, pureed']),
            $this->portion('cup', 160, ['food_source_record_id' => null, 'food_form_id' => null, 'is_default' => true]),
        ];

        $this->assertEqualsWithDelta(160.0, $this->convert('cup', 1, $portions)->grams, 1e-9);
    }

    public function test_two_defaults_with_different_weights_are_ambiguous(): void
    {
        $portions = [
            $this->portion('cup', 152, ['is_default' => true]),
            $this->portion('cup', 232, ['is_default' => true]),
        ];

        $this->assertSame(GramsConversion::AMBIGUOUS_PORTION, $this->convert('cup', 1, $portions)->status);
    }

    public function test_higher_confidence_settles_conflicting_plain_portions(): void
    {
        $portions = [
            $this->portion('cup', 164, ['confidence_level' => 'reference']),
            $this->portion('cup', 172, ['confidence_level' => 'measured']),
        ];

        $this->assertEqualsWithDelta(172.0, $this->convert('cup', 1, $portions)->grams, 1e-9);
    }

    // ------------------------------------------------------------------ portions that must not count

    public function test_portions_of_rejected_or_superseded_records_are_ignored(): void
    {
        $portions = [
            $this->portion('cup', 999, ['food_source_record_id' => 98, 'record_status' => 'rejected']),
            $this->portion('cup', 888, ['food_source_record_id' => 97, 'record_status' => 'superseded']),
        ];

        $this->assertSame(GramsConversion::NO_PORTION, $this->convert('cup', 1, $portions)->status);
    }

    public function test_the_own_record_portion_counts_whatever_the_record_status(): void
    {
        $own = $this->portion('cup', 164, ['record_status' => 'pending_review']);

        $this->assertEqualsWithDelta(164.0, $this->convert('cup', 1, [$own])->grams, 1e-9);
    }

    public function test_zero_weight_or_zero_amount_portions_are_ignored(): void
    {
        $portions = [
            $this->portion('cup', 0),
            $this->portion('cup', 164, ['amount' => 0.0]),
        ];

        $this->assertSame(GramsConversion::NO_PORTION, $this->convert('cup', 1, $portions)->status);
    }

    // ------------------------------------------------------------------ volume units

    public function test_ml_uses_a_portion_in_ml_directly(): void
    {
        $portion = $this->portion('ml', 91, ['amount' => 100.0]);

        $c = $this->convert('ml', 30, [$portion]);

        $this->assertEqualsWithDelta(27.3, $c->grams, 1e-9);
        $this->assertSame(GramsConversion::METHOD_PORTION, $c->method);
    }

    public function test_ml_uses_the_density_of_a_portion_in_another_volume_unit(): void
    {
        $flOz = $this->portion('fl_oz', 30.5);

        $c = $this->convert('ml', 100, [$flOz]);

        $this->assertEqualsWithDelta(100 * 30.5 / 29.57353, $c->grams, 1e-6);
        $this->assertSame(GramsConversion::METHOD_VOLUME_DENSITY, $c->method);
    }

    public function test_litres_scale_through_density(): void
    {
        $ml = $this->portion('ml', 92, ['amount' => 100.0]);

        $this->assertEqualsWithDelta(460.0, $this->convert('l', 0.5, [$ml])->grams, 1e-9);
    }

    public function test_ml_without_any_volume_portion_fails_with_a_volume_hint(): void
    {
        $cup = $this->portion('cup', 216); // household cup: its volume is not defined, so no density

        $c = $this->convert('ml', 30, [$cup]);

        $this->assertNull($c->grams);
        $this->assertSame(GramsConversion::NO_PORTION, $c->status);
        $this->assertStringContainsString('الكثافة', $c->message);
    }

    // ------------------------------------------------------------------ invalid input

    public function test_negative_amount_is_invalid(): void
    {
        $this->assertSame(GramsConversion::INVALID_AMOUNT, $this->convert('g', -5, [])->status);
    }

    public function test_zero_amount_is_zero_grams(): void
    {
        $this->assertSame(0.0, $this->convert('cup', 0, [$this->portion('cup', 164)])->grams);
    }

    public function test_household_unit_with_missing_record_fails(): void
    {
        $c = $this->resolver()->resolve(self::UNITS['cup'], null, [], 1);

        $this->assertSame(GramsConversion::UNKNOWN_RECORD, $c->status);
    }

    public function test_no_portion_message_names_the_unit_and_the_food(): void
    {
        $c = $this->convert('cup', 1, []);

        $this->assertSame(GramsConversion::NO_PORTION, $c->status);
        $this->assertStringContainsString('كوب', $c->message);
        $this->assertStringContainsString('حمص (مسلوق)', $c->message);
    }
}
