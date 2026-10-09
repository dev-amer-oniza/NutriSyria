<?php

namespace App\Services\Nutrition;

use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;

/**
 * Same logic as the computeGrams() we first wrote inline in RecipeIngredientsRelationManager
 * — extracted here so the live-preview column in that form and the actual calculation engine
 * can never silently drift apart into two different answers for the same input.
 */
final class GramsConverter
{
    public function convert(int $foodSourceRecordId, int $measureUnitId, float $amount): ?float
    {
        $unit = MeasureUnit::find($measureUnitId);
        if (! $unit) {
            return null;
        }

        if ($unit->code === 'g') {
            return $amount;
        }
        if ($unit->code === 'kg') {
            return $amount * 1000;
        }

        $foodId = FoodSourceRecord::find($foodSourceRecordId)?->food_id;
        if (! $foodId) {
            return null;
        }

        $portion = FoodPortion::where('food_id', $foodId)
            ->where('measure_unit_id', $measureUnitId)
            ->first();

        if (! $portion || (float) $portion->amount <= 0) {
            return null;
        }

        return ($amount / (float) $portion->amount) * (float) $portion->gram_weight;
    }
}
