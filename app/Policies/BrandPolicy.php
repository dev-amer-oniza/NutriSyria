<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Brand;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/** Brand owners see and edit only the brands they are linked to; creating and deleting is admin work. */
class BrandPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL) || $user->allows(Access::BRANDS_MANAGE_OWN);
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->update($user, $brand);
    }

    public function update(User $user, Brand $brand): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL)
            || ($user->allows(Access::BRANDS_MANAGE_OWN) && $user->managesBrand($brand->id));
    }

    public function create(User $user): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL);
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL);
    }

    public function deleteAny(User $user): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL);
    }
}
