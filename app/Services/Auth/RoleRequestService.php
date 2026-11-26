<?php

namespace App\Services\Auth;

use App\Auth\Access;
use App\Models\User;
use App\Notifications\SiteNotification;
use InvalidArgumentException;

/**
 * Role requests left from before the site signed up plain users only (the owner's decision):
 * the super-admin still grants or declines them from the users page, and the person hears the
 * answer in the site bell. No new request is made: a role is now given from the panel.
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

        $user->notify(SiteNotification::roleApproved($role));
    }

    public function decline(User $user): void
    {
        $role = $user->requested_role;
        $this->clear($user);

        if ($role) {
            $user->notify(SiteNotification::roleDeclined($role));
        }
    }

    private function clear(User $user): void
    {
        $user->forceFill(['requested_role' => null, 'role_requested_at' => null])->save();
    }
}
