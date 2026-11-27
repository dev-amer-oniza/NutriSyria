<?php

namespace App\Policies;

use App\Models\FoodRequest;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * "أكلات مطلوبة": whoever runs the Syrian kitchen (the admin, or «إدارة المطبخ السوري») reads
 * the dishes people asked for and answers them (the owner's decision 3a). Requests are sent from the site, never made in the panel;
 * the admin may delete them.
 */
class FoodRequestPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->managesKitchen();
    }

    public function view(User $user, FoodRequest $request): bool
    {
        return $user->managesKitchen();
    }

    /** Removing a request from the list, answered or not (people's day lines stay). */
    public function delete(User $user, FoodRequest $request): bool
    {
        return $user->managesKitchen();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesKitchen();
    }

    /** Link it to a meal, or say it cannot be added. */
    public function answer(User $user, FoodRequest $request): bool
    {
        return $user->managesKitchen() && $request->isPending();
    }
}
