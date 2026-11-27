<?php

namespace App\Services\Brands;

use App\Auth\Access;
use App\Models\Brand;
use App\Models\User;
use App\Notifications\SiteNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "إضافة عميل" (the owner's decision): the admin picks a signed-up account by its email and
 * makes it the one responsible for a restaurant or brand. A plain site account is given the
 * brand-owner role on the spot (decision 2a) and hears it in the site bell; the panel bell
 * tells them which client they now run (BrandLinks). Admins and nutritionists cannot own one.
 */
class ClientOwner
{
    /**
     * Who can be picked: active accounts that are plain users or already run a client.
     *
     * @return Builder<User>
     */
    public function candidates(): Builder
    {
        return User::query()
            ->where('is_active', true)
            // No role, or the brand-owner role and nothing else (not the admin, a nutritionist,
            // a reviewer or a role made from the panel).
            ->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', '!=', Access::BRAND_OWNER));
    }

    /** @return array<int, string> user id => "email · name", for the picker */
    public function options(): array
    {
        $options = [];
        foreach ($this->candidates()->orderBy('email')->get(['id', 'email', 'name']) as $user) {
            $options[(int) $user->id] = "{$user->email} · {$user->name}";
        }

        return $options;
    }

    /** The account that runs this client now (the first linked), if any. */
    public function of(Brand $brand): ?int
    {
        $id = $brand->managers()->orderBy('brand_user.id')->value('users.id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Make $userId the one responsible for the client: it replaces whoever ran it, and a plain
     * account becomes a brand owner. Returns the users linked before, for BrandLinks.
     *
     * @return list<int>
     */
    public function assign(Brand $brand, int $userId): array
    {
        $user = $this->candidates()->find($userId);
        if (! $user instanceof User || $brand->is_platform) {
            throw new InvalidArgumentException('This account cannot run a client.');
        }

        $before = array_map('intval', $brand->managers()->pluck('users.id')->all());

        DB::transaction(function () use ($brand, $user) {
            $brand->managers()->sync([$user->id]);

            if (! $user->hasRole(Access::BRAND_OWNER)) {
                $user->syncRoles([Access::BRAND_OWNER]);
                // A request left from the old sign-up is answered by this.
                $user->forceFill(['requested_role' => null, 'role_requested_at' => null])->save();
                $user->notify(SiteNotification::roleApproved(Access::BRAND_OWNER));
            }
        });

        return $before;
    }
}
