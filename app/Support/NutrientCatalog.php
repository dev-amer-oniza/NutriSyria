<?php

namespace App\Support;

/**
 * Single source of truth for the "core" USDA nutrient IDs used throughout
 * the app (Filament select options, imports, exports). Keeping this in one
 * place means the Select dropdown, the Excel import validation, and the
 * Excel export headings can never drift out of sync with each other.
 *
 * IDs match USDA FoodData Central's own nutrient.id values, so data
 * imported from the FDC API lines up directly with these keys.
 */
class NutrientCatalog
{
    /**
     * @return array<int, array{name: string, unit: string}>
     */
    public static function all(): array
    {
        return [
            1008 => ['name' => 'Energy', 'unit' => 'kcal'],
            1003 => ['name' => 'Protein', 'unit' => 'g'],
            1004 => ['name' => 'Total lipid (fat)', 'unit' => 'g'],
            1258 => ['name' => 'Saturated (fat)' , 'unit' => 'g'],
            1005 => ['name' => 'Carbohydrate, by difference', 'unit' => 'g'],
            1079 => ['name' => 'Fiber, total dietary', 'unit' => 'g'],
            2000 => ['name' => 'Sugars, total', 'unit' => 'g'],
            1087 => ['name' => 'Calcium, Ca', 'unit' => 'mg'],
            1089 => ['name' => 'Iron, Fe', 'unit' => 'mg'],
            1093 => ['name' => 'Sodium, Na', 'unit' => 'mg'],
            1092 => ['name' => 'Potassium, K', 'unit' => 'mg'],
            1162 => ['name' => 'Vitamin C, total ascorbic acid', 'unit' => 'mg'],
            1106 => ['name' => 'Vitamin A, RAE', 'unit' => 'µg'],
        ];
    }

    /**
     * Options for a Filament Select: [nutrient_id => "Name (unit)"].
     *
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return collect(self::all())
            ->mapWithKeys(fn (array $n, int $id) => [$id => "{$n['name']} ({$n['unit']})"])
            ->all();
    }

    public static function unitFor(int $nutrientId): ?string
    {
        return self::all()[$nutrientId]['unit'] ?? null;
    }

    public static function nameFor(int $nutrientId): ?string
    {
        return self::all()[$nutrientId]['name'] ?? null;
    }

    /**
     * Maps the wide-format spreadsheet column keys (as produced by
     * fetch_food_nutrients.py and normalized by Maatwebsite's heading
     * row parser) back to their USDA nutrient_id. Used when importing
     * a "one row per food, one column per nutrient" XLSX file.
     *
     * @return array<string, int>
     */
    public static function columnMap(): array
    {
        return [
            'energy_kcal' => 1008,
            'protein_g' => 1003,
            'fat_total_g' => 1004,
            'fat_saturated_g'=>1258,
            'carbs_g' => 1005,
            'fiber_g' => 1079,
            'sugars_total_g' => 2000,
            'calcium_mg' => 1087,
            'iron_mg' => 1089,
            'sodium_mg' => 1093,
            'potassium_mg' => 1092,
            'vitamin_c_mg' => 1162,
            'vitamin_a_rae_ug' => 1106,
        ];
    }
}
