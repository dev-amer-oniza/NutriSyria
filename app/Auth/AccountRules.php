<?php

namespace App\Auth;

use App\Models\User;
use App\Policies\UserPolicy;

/**
 * Account changes that would lock the system out or let someone raise their own rights. The
 * user form checks these before saving; each returns the Arabic reason, or null when allowed.
 */
final class AccountRules
{
    public static function roleChange(?User $target, ?string $newRole, ?User $actor): ?string
    {
        if ($target === null || ! $target->isSuperAdmin() || $newRole === Access::SUPER_ADMIN) {
            return null;
        }
        if ($actor !== null && $target->is($actor)) {
            return 'لا يمكنك إزالة دور مدير النظام عن حسابك.';
        }
        if (UserPolicy::isLastSuperAdmin($target)) {
            return 'هذا آخر مدير نظام فعّال: أضف مديراً آخر قبل تغيير دوره.';
        }

        return null;
    }

    public static function deactivation(?User $target, bool $active, ?User $actor): ?string
    {
        if ($target === null || $active) {
            return null;
        }
        if ($actor !== null && $target->is($actor)) {
            return 'لا يمكنك إيقاف حسابك.';
        }
        if (UserPolicy::isLastSuperAdmin($target)) {
            return 'هذا آخر مدير نظام فعّال: لا يمكن إيقافه.';
        }

        return null;
    }
}
