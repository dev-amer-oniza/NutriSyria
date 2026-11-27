<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Permission;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Permissions are locks the code checks (App\Auth\Access): their Arabic name and description
 * can be edited, but none is added or deleted from the panel (the owner's decision 2a).
 */
class PermissionPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }
}
