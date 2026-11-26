<?php

namespace App\Policies;

use App\Models\FoodRequest;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * "أكلات مطلوبة": the admin alone reads the dishes people asked for and answers them
 * (the owner's decision 3a). Requests are sent from the site, never made in the panel;
 * the admin may delete them.
 */
class FoodRequestPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, FoodRequest $request): bool
    {
        return $user->isSuperAdmin();
    }

    /** Removing a request from the list, answered or not (people's day lines stay). */
    public function delete(User $user, FoodRequest $request): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Link it to a meal, or say it cannot be added. */
    public function answer(User $user, FoodRequest $request): bool
    {
        return $user->isSuperAdmin() && $request->isPending();
    }
}
