<?php

namespace App\Services\Site;

use App\Enums\FoodSourceStatus;
use Illuminate\Support\Facades\DB;

/**
 * What the food database holds, as the "how we verify" page tells it (the owner's decisions
 * 2a 3a): approved foods per data source, and the foods that stand in for one USDA lacks
 * (proxies). Approved records only; read from the shared catalog cache.
 */
class DataCoverage
{
    public function __construct(private readonly PublicCatalogCache $cache) {}

    /**
     * Data sources with approved foods, best priority first. Restaurant recipes are left out:
     * they are what is calculated, not where the numbers come from.
     *
     * @return list<array{code: string, name: string, text: ?string, foods: int}>
     */
    public function sources(): array
    {
        return $this->cache->remember('coverage.sources', fn () => DB::table('data_sources as s')
            ->join('food_source_records as r', 'r.data_source_id', '=', 's.id')
            ->where('r.status', FoodSourceStatus::Active->value)
            ->where('s.type', '!=', 'recipe')
            ->groupBy('s.id', 's.code', 's.name', 's.priority')
            ->orderBy('s.priority')
            ->orderBy('s.name')
            ->get(['s.code', 's.name', DB::raw('count(distinct r.food_id) as foods')])
            ->map(fn ($s) => [
                'code' => $s->code,
                'name' => config("site.data_sources.{$s->code}.label", $s->name),
                'text' => config("site.data_sources.{$s->code}.text"),
                'foods' => (int) $s->foods,
            ])
            ->all());
    }

    /** Distinct foods with an approved record, whatever the source. */
    public function foods(): int
    {
        return $this->cache->remember('coverage.foods', fn () => (int) DB::table('food_source_records')
            ->where('status', FoodSourceStatus::Active->value)
            ->distinct()
            ->count('food_id'));
    }

    /**
     * Foods whose approved record is a stand-in, with what stands in for them, by name.
     *
     * @return list<array{food: string, note: string}>
     */
    public function proxies(): array
    {
        return $this->cache->remember('coverage.proxies', fn () => DB::table('food_source_records as r')
            ->join('foods as f', 'f.id', '=', 'r.food_id')
            ->where('r.status', FoodSourceStatus::Active->value)
            ->where('r.notes', 'like', MealCatalog::PROXY_NOTE_PREFIX.'%')
            ->orderBy('f.name_ar')
            ->get(['f.name_ar', 'r.notes'])
            ->map(fn ($r) => ['food' => $r->name_ar, 'note' => self::proxyNote($r->notes)])
            ->unique(fn (array $p) => $p['food'].'|'.$p['note'])
            ->values()
            ->all());
    }

    /**
     * The readable part of a proxy note: "مقاربة: زبادي يوناني بدل اللبنة | FDC: 170903"
     * gives "زبادي يوناني بدل اللبنة" (the source reference and later lines are internal).
     */
    public static function proxyNote(string $notes): string
    {
        $first = strtok($notes, "\n");
        $text = trim(explode('|', $first)[0]);
        $text = trim(mb_substr($text, mb_strlen(MealCatalog::PROXY_NOTE_PREFIX)), " :\t");

        return $text;
    }
}
