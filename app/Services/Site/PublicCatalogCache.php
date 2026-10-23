<?php

namespace App\Services\Site;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * The public catalog (approved meals, restaurants) built once and shared by every visitor
 * (the owner's decision 4a). Every entry lives under a version; any change to what the site
 * shows (a product, recipe, brand, food or approved value: AppServiceProvider) moves the
 * version, so the next visitor rebuilds it and a change shows at once. Old versions simply
 * expire. Personal verdicts are not cached: they are computed per person from this data.
 */
class PublicCatalogCache
{
    private const VERSION_KEY = 'site.catalog.version';

    /** Old versions are left to expire after this long. */
    private const TTL_SECONDS = 86400;

    /** @template T @param Closure(): T $build @return T */
    public function remember(string $name, Closure $build): mixed
    {
        return Cache::remember("site.catalog.{$this->version()}.{$name}", self::TTL_SECONDS, $build);
    }

    /** Called whenever public data changes. */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (string) hrtime(true));
    }

    private function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn () => (string) hrtime(true));
    }
}
