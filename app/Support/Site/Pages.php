<?php

namespace App\Support\Site;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * One page of a site list (meals, restaurants, nutritionists), cut from items already in memory
 * (the lists come from the public cache), keeping the search and filters in the page links. A
 * page number out of range, or not a number, falls back to the nearest real page.
 */
final class Pages
{
    public const PER_PAGE = 24;

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    public static function of(iterable $items, Request $request, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $items = collect($items)->values();
        $last = max(1, (int) ceil($items->count() / $perPage));
        $page = min(max(1, (int) filter_var($request->query('page'), FILTER_VALIDATE_INT, ['options' => ['default' => 1]])), $last);

        return (new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]))->appends(array_filter(
            $request->except('page'),
            // Search and filters only when set: the forms fill in their own defaults (suits=0).
            fn ($value) => ! in_array($value, [null, '', '0', 0, false], true),
        ));
    }
}
