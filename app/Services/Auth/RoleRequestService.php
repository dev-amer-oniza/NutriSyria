<?php

namespace App\Services\Auth;

use App\Auth\Access;
use App\Models\User;
use InvalidArgumentException;

/**
 * A brand owner or nutritionist who signed up on the site asks for a panel role; the
 * super-admin grants or declines it from the users page. Granting gives the account that one
 * role (one role per user), declining leaves it a plain site account.
 */
class RoleRequestService
{
    /** Only these can be asked for from the site; never the super-admin. */
    public const REQUESTABLE = [Access::BRAND_OWNER, Access::NUTRITIONIST];

    public function approve(User $user): void
    {
        $role = $user->requested_role;

        if (! in_array($role, self::REQUESTABLE, true)) {
            throw new InvalidArgumentException('لا يوجد طلب دور لهذا المستخدم.');
        }

        // Role first: should clearing the request fail, it simply stays pending and can be
        // approved again, with no account left half-changed.
        $user->syncRoles([$role]);
        $this->clear($user);
    }

    public function decline(User $user): void
    {
        $this->clear($user);
    }

    private function clear(User $user): void
    {
        $user->forceFill(['requested_role' => null, 'role_requested_at' => null])->save();
    }
}
