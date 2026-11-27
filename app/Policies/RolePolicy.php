<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Roles from the panel (the owner's decisions 1a 3a): new ones are made from the same
 * permissions, renamed and deleted. The built-in roles (Access::PANEL_ROLES) can have their
 * Arabic name and permissions changed but are never deleted, since the code relies on them;
 * the super-admin role is not edited at all (it needs no permissions). A role still given to
 * users is not deleted either (the delete action says how many).
 */
class RolePolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->allows(Access::USERS_MANAGE);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->allows(Access::USERS_MANAGE) && $role->name !== Access::SUPER_ADMIN;
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->allows(Access::USERS_MANAGE) && ! in_array($role->name, Access::PANEL_ROLES, true);
    }
}
