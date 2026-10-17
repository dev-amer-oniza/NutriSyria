<?php

namespace App\Services\Nutrition;

use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a recipe line ("2 cups of boiled chickpeas") into grams. Shared by the live preview in
 * RecipeIngredientsRelationManager and by the calculation engine, so the two can never drift
 * apart into two different answers for the same input.
 *
 * This class only loads data; the selection rules live in the pure PortionResolver.
 */
final class GramsConverter
{
    public function __construct(
        private readonly PortionResolver $resolver = new PortionResolver(),
    ) {
    }

    /** Grams, or null when the line cannot be converted (resolve() says why). */
    public function convert(int $foodSourceRecordId, int $measureUnitId, float $amount, ?int $chosenPortionId = null): ?float
    {
        return $this->resolve($foodSourceRecordId, $measureUnitId, $amount, $chosenPortionId)->grams;
    }

    public function resolve(int $foodSourceRecordId, int $measureUnitId, float $amount, ?int $chosenPortionId = null): GramsConversion
    {
        $loaded = $this->load($foodSourceRecordId, $measureUnitId);
        if ($loaded === null) {
            return GramsConversion::failed(GramsConversion::UNKNOWN_UNIT, 'وحدة القياس غير موجودة.');
        }

        return $this->resolver->resolve($loaded['unit'], $loaded['record'], $loaded['portions'], $amount, $chosenPortionId);
    }

    /**
     * The portion sizes a recipe line can pick between for this ingredient and unit
     * (see PortionResolver::choices()); empty when there is nothing to choose.
     *
     * @return list<array{id:int, label:string, grams:float}>
     */
    public function choices(int $foodSourceRecordId, int $measureUnitId): array
    {
        $loaded = $this->load($foodSourceRecordId, $measureUnitId);

        return $loaded === null ? [] : $this->resolver->choices($loaded['unit'], $loaded['record'], $loaded['portions']);
    }

    /** @return array{unit: array, record: ?array, portions: list<array>}|null  null when the unit does not exist */
    private function load(int $foodSourceRecordId, int $measureUnitId): ?array
    {
        $unit = MeasureUnit::find($measureUnitId);
        if (! $unit) {
            return null;
        }

        $unitData = [
            'id' => (int) $unit->id,
            'code' => $unit->code,
            'name_ar' => $unit->name_ar,
            'dimension' => $unit->dimension,
            'base_factor' => $unit->base_factor !== null ? (float) $unit->base_factor : null,
        ];

        // Mass units need no record and no portion.
        if (PortionResolver::massFactor($unitData) !== null) {
            return ['unit' => $unitData, 'record' => null, 'portions' => []];
        }

        $record = FoodSourceRecord::with(['food:id,name_ar', 'foodForm:id,name_ar'])->find($foodSourceRecordId);
        if (! $record) {
            return ['unit' => $unitData, 'record' => null, 'portions' => []];
        }

        $portions = FoodPortion::query()
            ->where('food_id', $record->food_id)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->with(['measureUnit', 'foodForm:id,name_ar', 'sourceRecord:id,food_form_id,status', 'sourceRecord.foodForm:id,name_ar'])
            ->orderBy('id')
            ->get();

        $aliases = MeasureUnitAlias::query()
            ->whereIn('measure_unit_id', $portions->pluck('measure_unit_id')->unique())
            ->get(['measure_unit_id', 'label'])
            ->groupBy('measure_unit_id');

        $portionData = $portions->map(function (FoodPortion $p) use ($aliases) {
            $u = $p->measureUnit;
            // A portion entered on a source record's page has no form of its own: it inherits the record's.
            $formId = $p->food_form_id ?? $p->sourceRecord?->food_form_id;
            $recordId = $p->food_source_record_id;
            $formName = $p->food_form_id !== null ? $p->foodForm?->name_ar : $p->sourceRecord?->foodForm?->name_ar;

            return [
                'id' => (int) $p->id,
                'measure_unit_id' => (int) $p->measure_unit_id,
                'unit_name_ar' => $u?->name_ar ?? '',
                'unit_dimension' => $u?->dimension,
                'unit_base_factor' => $u?->base_factor !== null ? (float) $u->base_factor : null,
                'unit_labels' => array_values(array_filter([
                    $u?->code, $u?->name_en, $u?->name_ar,
                    ...$aliases->get($p->measure_unit_id, collect())->pluck('label')->all(),
                ])),
                'amount' => (float) $p->amount,
                'gram_weight' => (float) $p->gram_weight,
                // Imports used to drop a bare size word ("small") because it is also a unit alias;
                // the source label survives in evidence.modifier.
                'description' => $p->description ?? ($p->evidence['modifier'] ?? null),
                'food_source_record_id' => $recordId !== null ? (int) $recordId : null,
                'food_form_id' => $formId !== null ? (int) $formId : null,
                'form_name_ar' => $formName,
                'record_status' => $p->sourceRecord?->status?->value,
                'is_default' => (bool) $p->is_default,
                'confidence_level' => $p->confidence_level?->value,
            ];
        })->all();

        return ['unit' => $unitData, 'record' => [
            'id' => (int) $record->id,
            'food_form_id' => $record->food_form_id !== null ? (int) $record->food_form_id : null,
            'food_name_ar' => $record->food?->name_ar,
            'form_name_ar' => $record->foodForm?->name_ar,
        ], 'portions' => $portionData];
    }
}
