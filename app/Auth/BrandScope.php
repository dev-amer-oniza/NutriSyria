<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Narrows brand and product queries to what the signed-in user may see. Used by the resources
 * (lists, record pages, global search), the brand pickers and the brand filter, so a brand
 * owner never receives another brand's rows, not even its name in a dropdown.
 */
final class BrandScope
{
    /** Brand ids the user is limited to, or null when they may see every brand. */
    public static function brandIds(?User $user, bool $readOnly = false): ?array
    {
        if ($user === null) {
            return [];
        }
        if ($user->allows(Access::BRANDS_MANAGE_ALL) || ($readOnly && $user->allows(Access::PRODUCTS_VIEW_ALL))) {
            return null;
        }

        return $user->allows(Access::BRANDS_MANAGE_OWN) ? $user->managedBrandIds() : [];
    }

    public static function brands(Builder $query, bool $readOnly = false): Builder
    {
        $ids = self::brandIds(auth()->user(), $readOnly);

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn('id'), $ids);
    }

    public static function products(Builder $query): Builder
    {
        $ids = self::brandIds(auth()->user(), readOnly: true);

        return $ids === null ? $query : $query->whereIn($query->qualifyColumn('brand_id'), $ids);
    }
}
