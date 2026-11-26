<?php

namespace App\Services\Auth;

use App\Auth\Access;
use App\Filament\Clusters\AccessControl\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\SiteNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use InvalidArgumentException;

/**
 * A brand owner or nutritionist who signed up on the site asks for a panel role; the
 * super-admin grants or declines it from the users page. Granting gives the account that one
 * role (one role per user), declining leaves it a plain site account.
 *
 * Who hears: the admins, in the panel bell, once a request is real (the email confirmed, or a
 * Google account); the person, in the site bell, of the answer either way.
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

    /** A sign-up asking for a role is ready to answer: every active admin hears, with the users page. */
    public function announce(User $user): void
    {
        $role = $user->requested_role;
        if (! in_array($role, self::REQUESTABLE, true)) {
            return;
        }

        $admins = User::role(Access::SUPER_ADMIN)->where('is_active', true)->get();
        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title("طلب حساب جديد: {$user->name}")
            ->body('يطلب دور «'.Access::roles()[$role].'». '.($user->phone ? "واتساب: {$user->phone}" : ''))
            ->icon('heroicon-o-user-plus')
            ->warning()
            ->actions([Action::make('open')->label('طلبات الأدوار')->url(UserResource::getUrl())->markAsRead()])
            ->sendToDatabase($admins);
    }

    private function clear(User $user): void
    {
        $user->forceFill(['requested_role' => null, 'role_requested_at' => null])->save();
    }
}
