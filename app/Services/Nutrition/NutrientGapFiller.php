<?php

namespace App\Services\Nutrition;

/**
 * Pure gap filling: no database, no Eloquent. The admin picks ONE source record per recipe
 * line (e.g. butter from USDA Foundation); when that analysis lacks a nutrient, this fills it
 * from the same food in the same form, taken from the other approved sources in priority order
 * (e.g. butter from SR Legacy). Every filled value says where it came from.
 *
 * Rules (agreed with the nutrition team):
 *  - only nutrients the chosen record does not report are filled; its own values always win;
 *  - missing energy (kcal) is first COMPUTED from the record's own protein, fat and
 *    carbohydrate (4/9/4), so it stays consistent with the rest of that analysis; it is borrowed
 *    only when one of the three is missing too;
 *  - one value per nutrient from one donor: never averaged or mixed;
 *  - donors are already limited by the caller to approved records of the same food and form.
 */
final class NutrientGapFiller
{
    private const ATWATER = ['protein_g' => 4.0, 'fat_g' => 9.0, 'carbohydrate_g' => 4.0];

    /**
     * @param  array<int, array{amount: float, unit: string}>  $own  the chosen record's values, keyed by nutrient_id
     * @param  list<array{source: string, values: array<int, array{amount: float, unit: string}>}>  $donors
     *     other records of the same food and form, best (lowest priority number) first
     * @param  array<string, int>  $idByCode  nutrient code => nutrient_id for every nutrient to report
     * @return array<int, array{amount: float, unit: string, origin?: array{type: string, source?: string}}>
     */
    public function fill(array $own, array $donors, array $idByCode): array
    {
        $result = $own;

        $energyId = $idByCode['energy_kcal'] ?? null;
        if ($energyId !== null && ! isset($result[$energyId])) {
            $kcal = 0.0;
            foreach (self::ATWATER as $code => $factor) {
                $id = $idByCode[$code] ?? null;
                if ($id === null || ! isset($own[$id])) {
                    $kcal = null;
                    break;
                }
                $kcal += $factor * $own[$id]['amount'];
            }
            if ($kcal !== null) {
                $result[$energyId] = ['amount' => round($kcal, 6), 'unit' => 'kcal', 'origin' => ['type' => 'computed']];
            }
        }

        foreach ($idByCode as $nutrientId) {
            if (isset($result[$nutrientId])) {
                continue;
            }
            foreach ($donors as $donor) {
                if (isset($donor['values'][$nutrientId])) {
                    $result[$nutrientId] = $donor['values'][$nutrientId]
                        + ['origin' => ['type' => 'borrowed', 'source' => $donor['source']]];
                    break;
                }
            }
        }

        return $result;
    }
}
