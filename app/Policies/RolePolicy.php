<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * The three roles are fixed (the code relies on their names): they can be viewed and their
 * permissions changed, but not created, renamed or deleted. The super-admin role needs no
 * permissions, so it is not editable either.
 */
class RolePolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool { return $user->allows(Access::USERS_MANAGE); }
    public function view(User $user, Role $role): bool { return $user->allows(Access::USERS_MANAGE); }

    public function update(User $user, Role $role): bool
    {
        return $user->allows(Access::USERS_MANAGE) && $role->name !== Access::SUPER_ADMIN;
    }
}
