<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;

/**
 * In-memory copy of the reference data an import needs. Loading once per run keeps
 * validation fast and lets the validator be tested without a database.
 */
final class Lookups
{
    /** @var array<string,int> data_sources.code => id */
    public array $dataSources = [];

    /** @var array<string,int> food_forms.code => id */
    public array $forms = [];

    /** @var array<string,array{id:int,unit:string,is_core:bool}> nutrients.code => info */
    public array $nutrients = [];

    /** @var array<string,int> normalized unit label => measure_units.id */
    public array $unitAliases = [];

    /** @var array<string,list<int>> normalized Arabic name (food or alias) => food ids */
    public array $foodsByName = [];

    /** @var array<int,true> */
    public array $foodIds = [];

    /** @var array<int,array<string,int>> parent id (0 = root) => [normalized name => category id] */
    public array $categoryChildren = [];

    public static function load(): self
    {
        $l = new self();

        foreach (DB::table('data_sources')->where('is_active', true)->get(['id', 'code']) as $r) {
            $l->dataSources[$r->code] = (int) $r->id;
        }
        foreach (DB::table('food_forms')->where('is_active', true)->get(['id', 'code']) as $r) {
            $l->forms[$r->code] = (int) $r->id;
        }
        foreach (DB::table('nutrients')->where('is_active', true)->get(['id', 'code', 'unit', 'is_core']) as $r) {
            $l->nutrients[$r->code] = ['id' => (int) $r->id, 'unit' => $r->unit, 'is_core' => (bool) $r->is_core];
        }
        foreach (DB::table('measure_unit_aliases')->get(['label', 'measure_unit_id']) as $r) {
            $l->unitAliases[mb_strtolower(trim($r->label))] = (int) $r->measure_unit_id;
        }
        foreach (DB::table('foods')->get(['id', 'name_ar']) as $r) {
            $l->rememberFood((int) $r->id, $r->name_ar);
        }
        foreach (DB::table('food_aliases')->get(['food_id', 'name_ar']) as $r) {
            $l->foodsByName[ArabicNormalizer::normalize($r->name_ar)][] = (int) $r->food_id;
        }
        foreach (DB::table('food_categories')->get(['id', 'parent_id', 'name_ar', 'name_en']) as $r) {
            $parent = (int) ($r->parent_id ?? 0);
            $l->categoryChildren[$parent][ArabicNormalizer::normalize($r->name_ar)] = (int) $r->id;
            if ($r->name_en) {
                $l->categoryChildren[$parent][ArabicNormalizer::normalize($r->name_en)] = (int) $r->id;
            }
        }

        return $l;
    }

    public function rememberFood(int $id, string $nameAr): void
    {
        $this->foodIds[$id] = true;
        $this->foodsByName[ArabicNormalizer::normalize($nameAr)][] = $id;
    }

    /**
     * Decide which food a record row refers to.
     *
     * @param  array<string,string>  $raw
     * @return array{status:string,id:?int,code:?string,message:?string}  status: existing | new | error
     */
    public function resolveFood(array $raw): array
    {
        $id = trim($raw['food_id'] ?? '');
        if ($id !== '') {
            if (! ctype_digit($id) || ! isset($this->foodIds[(int) $id])) {
                return ['status' => 'error', 'id' => null, 'code' => 'unknown_food_id', 'message' => "food_id '{$id}' does not exist"];
            }

            return ['status' => 'existing', 'id' => (int) $id, 'code' => null, 'message' => null];
        }

        $name = trim($raw['food_name_ar'] ?? '');
        if ($name === '') {
            return ['status' => 'error', 'id' => null, 'code' => 'missing_food', 'message' => 'Give food_id or food_name_ar'];
        }

        $ids = array_values(array_unique($this->foodsByName[ArabicNormalizer::normalize($name)] ?? []));
        if (count($ids) === 0) {
            return ['status' => 'new', 'id' => null, 'code' => null, 'message' => null];
        }
        if (count($ids) > 1) {
            return ['status' => 'error', 'id' => null, 'code' => 'ambiguous_food',
                'message' => "'{$name}' matches foods ".implode(', ', $ids).'; set food_id to choose one'];
        }

        return ['status' => 'existing', 'id' => $ids[0], 'code' => null, 'message' => null];
    }

    /** "legumes > chickpeas" => category id, or null when any step does not exist. */
    public function resolveCategoryPath(string $path): ?int
    {
        $parent = 0;
        foreach (explode('>', $path) as $segment) {
            $key = ArabicNormalizer::normalize($segment);
            if ($key === '' || ! isset($this->categoryChildren[$parent][$key])) {
                return null;
            }
            $parent = $this->categoryChildren[$parent][$key];
        }

        return $parent === 0 ? null : $parent;
    }

    /**
     * Unit label => measure_units.id. Sources often glue a description to the unit:
     * "cup, chopped", "slice, large (1/4" thick)", "tbsp chopped". Try the exact label first,
     * then the label without brackets, the part before the first comma, the first two words
     * and the first word, so one alias ("cup") covers all of its variants.
     */
    public function resolveUnitLabel(string $label): ?int
    {
        $clean = mb_strtolower(trim($label));
        if (isset($this->unitAliases[$clean])) {
            return $this->unitAliases[$clean];
        }

        $plain = preg_replace('/\([^)]*\)/u', '', $clean) ?? $clean;
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain, ' ,');
        $words = preg_split('/[\s,]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $candidates = [$plain, trim(explode(',', $plain)[0])];
        if (count($words) >= 2) {
            $candidates[] = $words[0].' '.$words[1];
        }
        if ($words) {
            $candidates[] = $words[0];
        }
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset($this->unitAliases[$candidate])) {
                return $this->unitAliases[$candidate];
            }
        }

        return null;
    }

    /** True when the label is itself a known alias (no extra description hidden in it). */
    public function isExactUnitLabel(string $label): bool
    {
        return isset($this->unitAliases[mb_strtolower(trim($label))]);
    }
}
