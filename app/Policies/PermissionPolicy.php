<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Permission;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/** The permission list comes from App\Auth\Access: it can be read, never edited from the panel. */
class PermissionPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool { return $user->allows(Access::USERS_MANAGE); }
    public function view(User $user, Permission $permission): bool { return $user->allows(Access::USERS_MANAGE); }
}
