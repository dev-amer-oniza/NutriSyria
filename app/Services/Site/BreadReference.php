<?php

namespace App\Services\Site;

use App\Enums\FoodSourceStatus;
use App\Models\FoodNutrientValue;
use App\Models\FoodSourceRecord;

/**
 * What one loaf of Arabic bread adds to a dish "eaten with bread" (the owner's request to make
 * the bread note clearer): the energy and carbohydrate of a 60 g loaf, from the approved
 * "خبز عربي" record (highest-priority source first). Null when the food is not in the base:
 * the note is then shown without numbers. Kept with the public catalog.
 */
class BreadReference
{
    public const FOOD = 'خبز عربي';

    public const LOAF_GRAMS = 60;

    public function __construct(private readonly PublicCatalogCache $cache) {}

    /** @return array{grams: int, kcal: int, carbs: int}|null */
    public function loaf(): ?array
    {
        return $this->cache->remember('bread.loaf', function (): ?array {
            $record = FoodSourceRecord::query()
                ->join('foods', 'foods.id', '=', 'food_source_records.food_id')
                ->join('data_sources', 'data_sources.id', '=', 'food_source_records.data_source_id')
                ->where('foods.name_ar', self::FOOD)
                ->where('food_source_records.status', FoodSourceStatus::Active)
                ->where('data_sources.is_active', true)
                ->orderBy('data_sources.priority')
                ->select('food_source_records.id')
                ->first();
            if ($record === null) {
                return null;
            }

            $values = FoodNutrientValue::query()
                ->join('nutrients', 'nutrients.id', '=', 'food_nutrient_values.nutrient_id')
                ->where('food_source_record_id', $record->id)
                ->whereIn('nutrients.code', ['energy_kcal', 'carbohydrate_g'])
                ->pluck('food_nutrient_values.amount_per_100g', 'nutrients.code');
            if (! isset($values['energy_kcal'], $values['carbohydrate_g'])) {
                return null;
            }

            $share = self::LOAF_GRAMS / 100;

            return [
                'grams' => self::LOAF_GRAMS,
                'kcal' => (int) round($values['energy_kcal'] * $share),
                'carbs' => (int) round($values['carbohydrate_g'] * $share),
            ];
        });
    }
}
