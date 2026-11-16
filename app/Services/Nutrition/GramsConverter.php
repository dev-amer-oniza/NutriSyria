<?php

namespace App\Services\Nutrition;

use App\Models\FoodPortion;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\MeasureUnitAlias;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
        private readonly PortionResolver $resolver = new PortionResolver,
    ) {}

    /** Grams, or null when the line cannot be converted (resolve() says why). */
    public function convert(int $foodSourceRecordId, int $measureUnitId, float $amount, ?int $chosenPortionId = null): ?float
    {
        return $this->resolve($foodSourceRecordId, $measureUnitId, $amount, $chosenPortionId)->grams;
    }

    public function resolve(int $foodSourceRecordId, int $measureUnitId, float $amount, ?int $chosenPortionId = null): GramsConversion
    {
        return $this->resolveMany([[
            'record_id' => $foodSourceRecordId, 'unit_id' => $measureUnitId, 'amount' => $amount, 'portion_id' => $chosenPortionId,
        ]])[0];
    }

    /**
     * Every line of a recipe at once, with a fixed number of queries however many lines there
     * are (units, records, portions and aliases each loaded once), so a long recipe costs no
     * more round trips than a short one. Same rules as resolve(), line by line, in order.
     *
     * @param  list<array{record_id: int, unit_id: int, amount: float, portion_id: ?int}>  $lines
     * @return list<GramsConversion>
     */
    public function resolveMany(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $units = MeasureUnit::whereKey(array_unique(array_column($lines, 'unit_id')))->get()
            ->mapWithKeys(fn (MeasureUnit $u) => [(int) $u->id => self::unitData($u)]);

        // Mass units need no record and no portion: only the other lines' records are loaded.
        $needRecords = array_filter($lines, fn (array $l) => isset($units[$l['unit_id']])
            && PortionResolver::massFactor($units[$l['unit_id']]) === null);
        $records = $this->loadRecords(array_column($needRecords, 'record_id'));

        return array_map(function (array $line) use ($units, $records): GramsConversion {
            $unit = $units[$line['unit_id']] ?? null;
            if ($unit === null) {
                return GramsConversion::failed(GramsConversion::UNKNOWN_UNIT, 'وحدة القياس غير موجودة.');
            }
            $loaded = PortionResolver::massFactor($unit) !== null
                ? ['record' => null, 'portions' => []]
                : ($records[$line['record_id']] ?? ['record' => null, 'portions' => []]);

            return $this->resolver->resolve($unit, $loaded['record'], $loaded['portions'], (float) $line['amount'], $line['portion_id']);
        }, $lines);
    }

    /**
     * The units a visitor can measure each record in (its portions' units, plus grams and
     * kilograms), each with the grams of one unit, or the sizes to choose from when one unit
     * has several weights. For the site's recipe form, which shows "= … غرام" while typing:
     * the server still converts every line again when the recipe is analysed.
     *
     * @param  list<int>  $recordIds
     * @param  list<string>  $massCodes  the mass units offered for every record
     * @return array<int, list<array{id: int, name: string, grams: ?float, choices: list<array{id: int, label: string, grams: float}>, default: ?int}>>
     */
    public function unitOptions(array $recordIds, array $massCodes = ['g', 'kg']): array
    {
        $records = $this->loadRecords($recordIds);
        $unitIds = [];
        foreach ($records as $loaded) {
            foreach ($loaded['portions'] as $p) {
                $unitIds[$p['measure_unit_id']] = true;
            }
        }
        $units = MeasureUnit::query()
            ->whereKey(array_keys($unitIds))
            ->orWhereIn('code', $massCodes)
            ->get()
            ->mapWithKeys(fn (MeasureUnit $u) => [(int) $u->id => self::unitData($u)]);
        $mass = $units->filter(fn (array $u) => in_array($u['code'], $massCodes, true));

        $options = [];
        foreach ($records as $recordId => $loaded) {
            $list = [];
            foreach ($mass as $unit) {
                $list[] = ['id' => $unit['id'], 'name' => $unit['name_ar'], 'grams' => PortionResolver::massFactor($unit), 'choices' => [], 'default' => null];
            }
            foreach (array_unique(array_column($loaded['portions'], 'measure_unit_id')) as $unitId) {
                $unit = $units[$unitId] ?? null;
                if ($unit === null || $mass->has($unitId)) {
                    continue;
                }
                $one = $this->resolver->resolve($unit, $loaded['record'], $loaded['portions'], 1.0);
                $choices = $this->resolver->choices($unit, $loaded['record'], $loaded['portions']);
                if (! $one->ok() && $choices === []) {
                    continue; // only another form's portion: never offered
                }
                $list[] = ['id' => $unit['id'], 'name' => $unit['name_ar'], 'grams' => $one->grams, 'choices' => $choices, 'default' => $one->ok() ? $one->portionId : null];
            }
            $options[$recordId] = $list;
        }

        return $options;
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

    /**
     * The units in which this record's portions cannot be told apart (several weights, no
     * default), each with the portions to choose from. A food that is used by cups and by pieces
     * may be ambiguous in one unit and not the other.
     *
     * @return list<array{unit_id:int, unit_name_ar:string, message:string, choices:list<array{id:int, label:string, grams:float}>}>
     */
    public function ambiguousUnits(int $foodSourceRecordId): array
    {
        $loaded = $this->loadRecord($foodSourceRecordId);
        if ($loaded === null) {
            return [];
        }

        $unitIds = array_values(array_unique(array_map(fn (array $p) => $p['measure_unit_id'], $loaded['portions'])));
        $out = [];
        foreach (MeasureUnit::whereKey($unitIds)->orderBy('id')->get() as $unit) {
            $unitData = self::unitData($unit);
            $conversion = $this->resolver->resolve($unitData, $loaded['record'], $loaded['portions'], 1.0);
            if ($conversion->status === GramsConversion::AMBIGUOUS_PORTION) {
                $out[] = [
                    'unit_id' => $unitData['id'],
                    'unit_name_ar' => $unitData['name_ar'],
                    'message' => $conversion->message,
                    'choices' => $this->resolver->choices($unitData, $loaded['record'], $loaded['portions']),
                ];
            }
        }

        return $out;
    }

    /**
     * Marks $portionId as the default of its unit for this record, and clears the other
     * defaults it competes with. False (nothing written) when it is not one of the record's
     * choices in that unit.
     */
    public function makeDefault(int $foodSourceRecordId, int $measureUnitId, int $portionId): bool
    {
        $loaded = $this->load($foodSourceRecordId, $measureUnitId);
        if ($loaded === null || $loaded['record'] === null) {
            return false;
        }
        $clear = $this->resolver->defaultSwap($loaded['unit'], $loaded['record'], $loaded['portions'], $portionId);
        if ($clear === null) {
            return false;
        }

        DB::transaction(function () use ($clear, $portionId) {
            FoodPortion::whereKey($clear)->where('is_default', true)->get()->each->update(['is_default' => false]);
            FoodPortion::findOrFail($portionId)->update(['is_default' => true]);
        });

        return true;
    }

    /** @return array{unit: array, record: ?array, portions: list<array>}|null  null when the unit does not exist */
    private function load(int $foodSourceRecordId, int $measureUnitId): ?array
    {
        $unit = MeasureUnit::find($measureUnitId);
        if (! $unit) {
            return null;
        }
        $unitData = self::unitData($unit);

        // Mass units need no record and no portion.
        if (PortionResolver::massFactor($unitData) !== null) {
            return ['unit' => $unitData, 'record' => null, 'portions' => []];
        }

        return ['unit' => $unitData] + ($this->loadRecord($foodSourceRecordId) ?? ['record' => null, 'portions' => []]);
    }

    private static function unitData(MeasureUnit $unit): array
    {
        return [
            'id' => (int) $unit->id,
            'code' => $unit->code,
            'name_ar' => $unit->name_ar,
            'dimension' => $unit->dimension,
            'base_factor' => $unit->base_factor !== null ? (float) $unit->base_factor : null,
        ];
    }

    /** @return array{record: array, portions: list<array>}|null  null when the record does not exist */
    private function loadRecord(int $foodSourceRecordId): ?array
    {
        return $this->loadRecords([$foodSourceRecordId])[$foodSourceRecordId] ?? null;
    }

    /**
     * Records with the portions of their foods, for many records in one go.
     *
     * @param  list<int>  $recordIds
     * @return array<int, array{record: array, portions: list<array>}> keyed by record id; missing records left out
     */
    private function loadRecords(array $recordIds): array
    {
        $recordIds = array_values(array_unique(array_map('intval', $recordIds)));
        if ($recordIds === []) {
            return [];
        }

        $records = FoodSourceRecord::with(['food:id,name_ar', 'foodForm:id,name_ar'])->whereKey($recordIds)->get();
        if ($records->isEmpty()) {
            return [];
        }

        $portions = FoodPortion::query()
            ->whereIn('food_id', $records->pluck('food_id')->unique())
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->with(['measureUnit', 'foodForm:id,name_ar', 'sourceRecord:id,food_form_id,status', 'sourceRecord.foodForm:id,name_ar'])
            ->orderBy('id')
            ->get();

        /** @var array<int, list<string>> $aliases measure unit id => its alias labels */
        $aliases = [];
        foreach (MeasureUnitAlias::query()->whereIn('measure_unit_id', $portions->pluck('measure_unit_id')->unique())->get(['measure_unit_id', 'label']) as $alias) {
            $aliases[(int) $alias->measure_unit_id][] = (string) $alias->label;
        }

        /** @var array<int, list<array<string, mixed>>> $portionsByFood */
        $portionsByFood = [];
        foreach ($portions as $portion) {
            $portionsByFood[(int) $portion->food_id][] = self::portionData($portion, $aliases[(int) $portion->measure_unit_id] ?? []);
        }

        $loaded = [];
        foreach ($records as $record) {
            $loaded[(int) $record->id] = [
                'record' => [
                    'id' => (int) $record->id,
                    'food_form_id' => $record->food_form_id !== null ? (int) $record->food_form_id : null,
                    'food_name_ar' => $record->food?->name_ar,
                    'form_name_ar' => $record->foodForm?->name_ar,
                ],
                'portions' => $portionsByFood[(int) $record->food_id] ?? [],
            ];
        }

        return $loaded;
    }

    /** @param  list<string>  $aliasLabels  the alias labels of the portion's unit */
    private static function portionData(FoodPortion $p, array $aliasLabels): array
    {
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
                ...$aliasLabels,
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
    }
}
