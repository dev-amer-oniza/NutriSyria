<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Brand;
use App\Models\Preparation;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * A preparation follows its brand, like its products: its owner writes it, a nutritionist may
 * read it. Not the admin's (the restaurant side is hidden from them), and never the platform
 * kitchen's (the owner's decision 4a: brands and restaurants only).
 */
class PreparationPolicy
{
    use DeniesByDefault;

    public function __construct(private readonly ProductPolicy $products = new ProductPolicy) {}

    public function viewAny(User $user): bool
    {
        return ! $user->isSuperAdmin() && $this->products->viewAny($user);
    }

    public function view(User $user, Preparation $preparation): bool
    {
        return $user->readsAllProducts() && ! $user->isSuperAdmin() && ! self::platform($preparation)
            || $this->update($user, $preparation);
    }

    public function create(User $user): bool
    {
        return ! $user->isSuperAdmin() && $this->products->create($user);
    }

    public function update(User $user, Preparation $preparation): bool
    {
        if ($user->isSuperAdmin() || self::platform($preparation)) {
            return false;
        }

        return $user->allows(Access::BRANDS_MANAGE_ALL)
            || ($user->allows(Access::BRANDS_MANAGE_OWN) && $user->managesBrand($preparation->brand_id));
    }

    public function delete(User $user, Preparation $preparation): bool
    {
        return $this->update($user, $preparation);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    private static function platform(Preparation $preparation): bool
    {
        return (bool) Brand::query()->whereKey($preparation->brand_id)->value('is_platform');
    }
}
