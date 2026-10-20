<?php

namespace App\Support\Site;

use Illuminate\Support\Facades\Route;

/** Links in config('site.*') name routes that may not exist yet. */
final class SiteLink
{
    public static function url(string $route): string
    {
        return Route::has($route) ? route($route) : '#';
    }

    public static function current(string $route): bool
    {
        return request()->routeIs($route);
    }
}
