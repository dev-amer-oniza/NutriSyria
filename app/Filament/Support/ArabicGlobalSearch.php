<?php

namespace App\Filament\Support;

use App\Support\ArabicSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/** The top search bar matches Arabic names whatever the spelling ("جبنه" finds "جبنة"). */
trait ArabicGlobalSearch
{
    protected static function applyGlobalSearchAttributeConstraints(Builder $query, string $search): void
    {
        ArabicSearch::constrain($query, Arr::flatten(static::getGloballySearchableAttributes()), $search);
    }
}
