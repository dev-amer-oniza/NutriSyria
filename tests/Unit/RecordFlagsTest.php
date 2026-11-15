<?php

namespace Tests\Unit;

use App\Data\Import\ReviewFlag;
use App\Services\Import\RecordFlags;
use PHPUnit\Framework\TestCase;

class RecordFlagsTest extends TestCase
{
    private function rules(): RecordFlags
    {
        return new RecordFlags([
            'energy_mismatch_pct' => 15,
            'large_change_pct' => 20,
            'energy_floor_kcal' => 10,
            'proximate_sum_tolerance_g' => 5,
            'rounding_tolerance_g' => 0.5,
        ]);
    }

    /** @param array<string, float|null> $amounts */
    private function values(array $amounts): array
    {
        return array_map(fn (?float $a) => ['amount' => $a], $amounts);
    }

    /** Raw lentils, SR Legacy 172420: consistent numbers. */
    private function lentils(array $over = []): array
    {
        return $this->values($over + [
            'energy_kcal' => 352, 'protein_g' => 24.63, 'fat_g' => 1.06, 'carbohydrate_g' => 63.35,
            'water_g' => 8.26, 'ash_g' => 2.71, 'fiber_g' => 10.7, 'sugars_g' => 2.03,
        ]);
    }

    private function codes(array $flags): array
    {
        return array_map(fn (ReviewFlag $f) => $f->code, $flags);
    }

    public function test_a_consistent_record_has_no_flag(): void
    {
        $this->assertSame([], $this->rules()->for($this->lentils(), 'FDC: 172420 (published 2018-04-01)'));
    }

    public function test_a_missing_macro_is_named(): void
    {
        $flags = $this->rules()->for($this->values(['energy_kcal' => 100, 'protein_g' => 5, 'carbohydrate_g' => 20]));

        $this->assertSame([ReviewFlag::MISSING_MACRO], $this->codes($flags));
        $this->assertSame('ناقص: الدهون', $flags[0]->label);
    }

    public function test_energy_is_flagged_only_past_fifteen_percent(): void
    {
        // 4*10 + 9*0 + 4*15 = 100 kcal computed.
        $at = fn (float $kcal) => $this->codes($this->rules()->for($this->values(['energy_kcal' => $kcal, 'protein_g' => 10, 'fat_g' => 0, 'carbohydrate_g' => 15])));

        $this->assertSame([], $at(117.6));   // gap 17.6 = 14.97% of 117.6
        $this->assertSame([ReviewFlag::ENERGY_MISMATCH], $at(118.0)); // gap 18 = 15.25%
        $this->assertSame([ReviewFlag::ENERGY_MISMATCH], $at(80));    // gap 20 = 25% of 80
    }

    public function test_a_small_gap_in_kcal_is_never_flagged(): void
    {
        // 4 kcal reported, 0 computed: 100% but only 4 kcal, under the 10 kcal floor (a spice, a drink).
        $flags = $this->rules()->for($this->values(['energy_kcal' => 4, 'protein_g' => 0, 'fat_g' => 0, 'carbohydrate_g' => 0]));

        $this->assertSame([], $flags);
    }

    public function test_the_energy_message_carries_both_numbers(): void
    {
        $flags = $this->rules()->for($this->values(['energy_kcal' => 350, 'protein_g' => 10, 'fat_g' => 10, 'carbohydrate_g' => 25]));

        $this->assertStringContainsString('350', $flags[0]->detail);
        $this->assertStringContainsString('230', $flags[0]->detail);
    }

    public function test_proximates_far_from_100_g(): void
    {
        $flags = $this->rules()->for($this->lentils(['water_g' => 20.0]));

        $this->assertSame([ReviewFlag::PROXIMATE_SUM], $this->codes($flags));
    }

    public function test_a_part_bigger_than_its_whole(): void
    {
        $flags = $this->rules()->for($this->lentils(['sugars_g' => 70.0]));

        $this->assertContains(ReviewFlag::PART_EXCEEDS_WHOLE, $this->codes($flags));
        $this->assertStringContainsString('السكريات', $flags[array_search(ReviewFlag::PART_EXCEEDS_WHOLE, $this->codes($flags))]->detail);
    }

    public function test_a_value_outside_the_sources_range(): void
    {
        $values = $this->lentils();
        $values['protein_g'] += ['min' => 25.0, 'max' => 28.0];

        $this->assertSame([ReviewFlag::OUTSIDE_RANGE], $this->codes($this->rules()->for($values)));
    }

    public function test_a_proxy_shows_its_note_without_the_fdc_part(): void
    {
        $flags = $this->rules()->for($this->lentils(), 'مقاربة: زبادي يوناني | FDC: 170903');

        $this->assertSame([ReviewFlag::PROXY], $this->codes($flags));
        $this->assertSame('مقاربة: زبادي يوناني', $flags[0]->detail);
    }

    public function test_only_a_change_above_twenty_percent_is_flagged(): void
    {
        $small = [['nutrient' => 'protein_g', 'old' => 24.0, 'new' => 28.7]];  // +19.6%
        $big = [['nutrient' => 'protein_g', 'old' => 24.0, 'new' => 28.9]];    // +20.4%

        $this->assertSame([], $this->rules()->for($this->lentils(), null, $small));
        $flags = $this->rules()->for($this->lentils(), null, $big);
        $this->assertSame([ReviewFlag::LARGE_CHANGE], $this->codes($flags));
        $this->assertStringContainsString('من 24 إلى 28.9', $flags[0]->detail);
    }

    public function test_a_value_appearing_from_zero_counts_as_a_change(): void
    {
        $flags = $this->rules()->for($this->lentils(), null, [['nutrient' => 'sugars_g', 'old' => 0.0, 'new' => 2.0]]);

        $this->assertSame([ReviewFlag::LARGE_CHANGE], $this->codes($flags));
    }
}
