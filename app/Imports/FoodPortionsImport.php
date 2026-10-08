<?php

namespace App\Imports;

use App\Models\Food;
use App\Models\FoodPortion;
use App\Models\MeasureUnit;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Expects columns: FDC ID, Measure Unit USDA ID, Measure Unit English Name,
 * Amount, Modifier, Gram Weight, Data Points
 * (matches FoodPortionsExport::headings()).
 *
 * Foods are looked up by fdc_id (never created here -- run FoodsImport first).
 * Measure units are looked up by usda_id when given, falling back to name_en
 * for tenant/local units -- never created here either, run MeasureUnitsImport
 * first so both sides of the relation already exist.
 *
 * Rows are upserted on (food_id, measure_unit_id, modifier), matching the
 * `food_unit_modifier_unique` DB constraint, so re-importing the same file
 * updates gram_weight/data_points instead of creating duplicate portions.
 */
class FoodPortionsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(SupportCollection $rows): void
    {
        foreach ($rows as $index => $row) {
            $fdcId = $row['fdc_id'] ?? null;

            if (blank($fdcId)) {
                $this->skipped++;
                $this->errors[] = "Row {$index}: missing FDC ID, skipped.";

                continue;
            }

            $food = Food::where('fdc_id', (int) $fdcId)->first();

            if (! $food) {
                $this->skipped++;
                $this->errors[] = "Row {$index}: no food found for FDC ID {$fdcId}, skipped.";

                continue;
            }

            $measureUnit = $this->resolveMeasureUnit($row);

            if (! $measureUnit) {
                $this->skipped++;
                $this->errors[] = "Row {$index} (FDC {$fdcId}): measure unit not found, skipped.";

                continue;
            }

            $amount = $row['amount'] ?? null;
            $gramWeight = $row['gram_weight'] ?? null;

            if (blank($gramWeight)) {
                $this->skipped++;
                $this->errors[] = "Row {$index} (FDC {$fdcId}): missing Gram Weight, skipped.";

                continue;
            }

            // Blank modifier -> null, to match the nullable column / unique index
            $modifier = blank($row['modifier'] ?? null) ? null : $row['modifier'];

            FoodPortion::updateOrCreate(
                [
                    'food_id' => $food->id,
                    'measure_unit_id' => $measureUnit->id,
                    'modifier' => $modifier,
                ],
                [
                    'amount' => blank($amount) ? 1 : $amount,
                    'gram_weight' => $gramWeight,
                    'data_points' => $row['data_points'] ?? 0,
                ]
            );

            $this->imported++;
        }
    }

    private function resolveMeasureUnit(SupportCollection|array $row): ?MeasureUnit
    {
        $usdaId = $row['measure_unit_usda_id'] ?? null;

        if (! blank($usdaId)) {
            return MeasureUnit::where('usda_id', (int) $usdaId)->first();
        }

        $nameEn = $row['measure_unit_english_name'] ?? null;

        if (blank($nameEn)) {
            return null;
        }

        return MeasureUnit::where('name_en', $nameEn)->first();
    }
}