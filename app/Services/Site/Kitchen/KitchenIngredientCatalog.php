<?php

namespace App\Services\Site\Kitchen;

use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use App\Services\Import\ArabicNormalizer;
use App\Services\Nutrition\GramsConverter;
use App\Services\Site\PublicCatalogCache;

/**
 * What a visitor can put in their own version of a kitchen dish: every approved ingredient
 * record (never a dish's own calculated values), each with the units it can be measured in.
 * Built once per catalog version (PublicCatalogCache), so searching it costs no query: an
 * approval anywhere renews it.
 */
class KitchenIngredientCatalog
{
    public function __construct(
        private readonly PublicCatalogCache $cache,
        private readonly GramsConverter $grams,
    ) {}

    /**
     * @return list<array{id: int, name: string, form: ?string, source: string, search: string,
     *     units: list<array{id: int, name: string, grams: ?float, choices: list<array{id: int, label: string, grams: float}>, default: ?int}>}>
     */
    public function all(): array
    {
        return $this->cache->remember('kitchen.ingredients', function (): array {
            $records = FoodSourceRecord::query()
                ->join('foods', 'foods.id', '=', 'food_source_records.food_id')
                ->join('data_sources', 'data_sources.id', '=', 'food_source_records.data_source_id')
                ->where('food_source_records.status', FoodSourceStatus::Active)
                ->where('data_sources.is_active', true)
                ->where('data_sources.type', '!=', 'recipe')
                ->orderBy('foods.name_ar')
                ->orderBy('data_sources.priority')
                ->orderBy('food_source_records.id')
                ->select('food_source_records.*')
                ->with(['food:id,name_ar,name_en', 'food.aliases:id,food_id,name_ar,name_en', 'foodForm:id,name_ar', 'dataSource:id,name'])
                ->get();

            $units = $this->grams->unitOptions($records->pluck('id')->all());

            return $records->map(fn (FoodSourceRecord $r) => [
                'id' => (int) $r->id,
                'name' => (string) $r->food?->name_ar,
                'form' => $r->foodForm?->name_ar,
                'source' => (string) $r->dataSource?->name,
                'search' => ArabicNormalizer::normalize(implode(' ', array_filter([
                    $r->food?->name_ar, $r->food?->name_en,
                    ...$r->food?->aliases->flatMap(fn ($a) => [$a->name_ar, $a->name_en])->all() ?? [],
                ]))),
                'units' => $units[$r->id] ?? [],
            ])->values()->all();
        });
    }

    /** @return array<int, true> the ids a visitor may use */
    public function ids(): array
    {
        return array_fill_keys(array_column($this->all(), 'id'), true);
    }
}
