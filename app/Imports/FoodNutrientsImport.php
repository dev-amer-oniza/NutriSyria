<?php

namespace App\Imports;

use App\Models\Food;
use App\Models\FoodNutrient;
use App\Support\NutrientCatalog;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Imports the WIDE-format spreadsheet produced by fetch_food_nutrients.py:
 * one row per food, with columns like:
 *   name_ar, fdcId, description, dataType,
 *   energy_kcal, protein_g, fat_total_g, carbs_g, fiber_g, sugars_total_g,
 *   calcium_mg, iron_mg, sodium_mg, potassium_mg, vitamin_c_mg, vitamin_a_rae_ug
 *
 * Maatwebsite's heading-row parser lowercases headers and strips
 * separators, so "FDC ID"/"fdcId" both normalize to "fdcid" and
 * "dataType" normalizes to "datatype" -- this class reads those exact
 * normalized keys.
 *
 * For each row: the food is matched (or upserted) by fdc_id, then each
 * nutrient column with a non-null value becomes its own FoodNutrient row,
 * keyed by (food_id, nutrient_id) via NutrientCatalog::columnMap() --
 * fdc_id itself is never written to food_nutrients.
 */
class FoodNutrientsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(SupportCollection $rows): void
    {
        $columnMap = NutrientCatalog::columnMap();

        foreach ($rows as $index => $row) {
            // Maatwebsite normalizes "FDC ID" / "fdcId" to "fdcid"
            $fdcId = $row['fdcid'] ?? $row['fdc_id'] ?? null;

            if (blank($fdcId)) {
                $this->skipped++;
                $this->errors[] = "Row {$index}: missing FDC ID, skipped.";

                continue;
            }

            // Upsert the food itself so this file can double as a foods import too
            $food = Food::updateOrCreate(
                ['fdc_id' => (int) $fdcId],
                array_filter([
                    'name_ar' => $row['name_ar'] ?? null,
                    'description' => $row['description'] ?? null,
                    'data_type' => $row['datatype'] ?? $row['data_type'] ?? null,
                ], fn ($value) => $value !== null)
            );

            $nutrientsWritten = 0;

            foreach ($columnMap as $columnKey => $nutrientId) {
                $amount = $row[$columnKey] ?? null;

                if (blank($amount)) {
                    continue; // this food just doesn't report that nutrient
                }

                FoodNutrient::updateOrCreate(
                    [
                        'food_id' => $food->id,
                        'nutrient_id' => $nutrientId,
                    ],
                    [
                        'nutrient_name' => NutrientCatalog::nameFor($nutrientId),
                        'unit' => NutrientCatalog::unitFor($nutrientId),
                        'amount' => $amount,
                    ]
                );

                $nutrientsWritten++;
            }

            if ($nutrientsWritten === 0) {
                $this->skipped++;
                $this->errors[] = "Row {$index} (FDC {$fdcId}): no nutrient values found.";

                continue;
            }

            $this->imported += $nutrientsWritten;
        }
    }
}
