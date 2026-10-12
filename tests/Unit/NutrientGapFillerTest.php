<?php

namespace Tests\Unit;

use App\Services\Nutrition\NutrientGapFiller;
use PHPUnit\Framework\TestCase;

class NutrientGapFillerTest extends TestCase
{
    private const IDS = ['energy_kcal' => 1, 'protein_g' => 2, 'fat_g' => 3, 'carbohydrate_g' => 4, 'fat_sat_g' => 5, 'sodium_mg' => 6];

    private function v(float $amount, string $unit = 'g'): array
    {
        return ['amount' => $amount, 'unit' => $unit];
    }

    private function fill(array $own, array $donors = []): array
    {
        return (new NutrientGapFiller())->fill($own, $donors, self::IDS);
    }

    public function test_the_chosen_record_values_always_win(): void
    {
        $r = $this->fill([1 => $this->v(717, 'kcal'), 5 => $this->v(51)], [
            ['source' => 'USDA SR Legacy', 'values' => [1 => $this->v(700, 'kcal'), 5 => $this->v(40)]],
        ]);

        $this->assertSame(717.0, $r[1]['amount']);
        $this->assertSame(51.0, $r[5]['amount']);
        $this->assertArrayNotHasKey('origin', $r[1]);
    }

    public function test_missing_energy_is_computed_from_the_records_own_macronutrients(): void
    {
        $r = $this->fill([2 => $this->v(10), 3 => $this->v(5), 4 => $this->v(20)], [
            ['source' => 'USDA SR Legacy', 'values' => [1 => $this->v(999, 'kcal')]],
        ]);

        $this->assertEqualsWithDelta(4 * 10 + 9 * 5 + 4 * 20, $r[1]['amount'], 1e-9);
        $this->assertSame(['type' => 'computed'], $r[1]['origin']);
    }

    public function test_energy_is_borrowed_when_a_macronutrient_is_missing_too(): void
    {
        // Foundation butter: fat only, no energy, no protein or carbohydrate.
        $r = $this->fill([3 => $this->v(81)], [
            ['source' => 'USDA SR Legacy', 'values' => [1 => $this->v(717, 'kcal'), 2 => $this->v(0.85), 4 => $this->v(0.06)]],
        ]);

        $this->assertSame(717.0, $r[1]['amount']);
        $this->assertSame(['type' => 'borrowed', 'source' => 'USDA SR Legacy'], $r[1]['origin']);
        $this->assertSame(0.85, $r[2]['amount']);
        $this->assertSame(81.0, $r[3]['amount']);
    }

    public function test_the_first_donor_that_has_the_value_wins_and_values_are_never_mixed(): void
    {
        $r = $this->fill([2 => $this->v(1)], [
            ['source' => 'Syrian lab', 'values' => [6 => $this->v(300, 'mg')]],
            ['source' => 'USDA SR Legacy', 'values' => [6 => $this->v(100, 'mg'), 5 => $this->v(2)]],
        ]);

        $this->assertSame(300.0, $r[6]['amount']);
        $this->assertSame('Syrian lab', $r[6]['origin']['source']);
        $this->assertSame(2.0, $r[5]['amount']);
        $this->assertSame('USDA SR Legacy', $r[5]['origin']['source']);
    }

    public function test_without_donors_missing_values_stay_missing(): void
    {
        $r = $this->fill([3 => $this->v(81)]);

        $this->assertSame([3], array_keys($r));
    }
}
