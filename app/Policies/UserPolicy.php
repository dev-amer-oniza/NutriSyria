<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Accounts are managed with users.manage. Two rules hold even for the super-admin, so nobody
 * locks the system out: you cannot delete yourself, and the last active super-admin cannot be
 * deleted (the user form also refuses to take that role away, see UserForm).
 */
class UserPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool { return $user->allows(Access::USERS_MANAGE); }
    public function view(User $user, User $target): bool { return $user->allows(Access::USERS_MANAGE); }
    public function create(User $user): bool { return $user->allows(Access::USERS_MANAGE); }
    public function update(User $user, User $target): bool { return $user->allows(Access::USERS_MANAGE); }

    public function delete(User $user, User $target): bool
    {
        return $user->allows(Access::USERS_MANAGE)
            && ! $user->is($target)
            && ! self::isLastSuperAdmin($target);
    }

    public static function isLastSuperAdmin(User $target): bool
    {
        return $target->isSuperAdmin()
            && User::role(Access::SUPER_ADMIN)->where('is_active', true)->whereKeyNot($target->getKey())->doesntExist();
    }
}
