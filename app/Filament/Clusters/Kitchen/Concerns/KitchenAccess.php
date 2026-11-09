<?php

namespace App\Filament\Clusters\Kitchen\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * The kitchen's resources share their models with the restaurants' (Product, ProductCategory),
 * whose policies keep the admin out of every restaurant list. Here it is the other way round:
 * the admin alone may list, add and order; a record is theirs only when it is the platform
 * kitchen's (and the resources' queries hold nothing else).
 */
trait KitchenAccess
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (! auth()->user()?->isSuperAdmin()) {
            return Response::deny();
        }

        return $record === null || $record->isPlatform() ? Response::allow() : Response::deny();
    }

    public static function canGloballySearch(): bool
    {
        return false;
    }
}
