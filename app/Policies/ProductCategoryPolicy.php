<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/** A menu section follows its brand, like the brand's products (ProductPolicy). */
class ProductCategoryPolicy
{
    use DeniesByDefault;

    public function __construct(private readonly ProductPolicy $products = new ProductPolicy())
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->products->viewAny($user);
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->allows(Access::PRODUCTS_VIEW_ALL) || $this->update($user, $category);
    }

    public function create(User $user): bool
    {
        // Which brand it goes to is the brand page it is added from, which needs update rights.
        return $this->products->create($user);
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL)
            || ($user->allows(Access::BRANDS_MANAGE_OWN) && $user->managesBrand($category->brand_id));
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $this->update($user, $category);
    }

    public function deleteAny(User $user): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->create($user);
    }
}
