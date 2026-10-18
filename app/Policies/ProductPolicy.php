<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Products follow their brand: a brand owner manages the products of their own brands. The
 * nutritionist sees every product (to review it) but changes none, and is the one who
 * approves the official nutrition values.
 */
class ProductPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL)
            || $user->allows(Access::BRANDS_MANAGE_OWN)
            || $user->allows(Access::PRODUCTS_VIEW_ALL);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->allows(Access::PRODUCTS_VIEW_ALL) || $this->update($user, $product);
    }

    public function create(User $user): bool
    {
        // Which brand it goes to is checked by the form (only the user's own brands).
        return $user->allows(Access::BRANDS_MANAGE_ALL) || $user->allows(Access::BRANDS_MANAGE_OWN);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->allows(Access::BRANDS_MANAGE_ALL)
            || ($user->allows(Access::BRANDS_MANAGE_OWN) && $user->managesBrand($product->brand_id));
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function deleteAny(User $user): bool
    {
        // Bulk delete: the table only lists the user's own products.
        return $this->create($user);
    }

    /** Save the analysis as the product's official nutrition values. */
    public function approveNutrition(User $user, Product $product): bool
    {
        return $user->allows(Access::NUTRITION_APPROVE) && $this->view($user, $product);
    }

    /** Send the brand owner a note about the analysis: whoever may approve it. */
    public function sendReviewNote(User $user, Product $product): bool
    {
        return $this->approveNutrition($user, $product);
    }

    /** Mark a note as fixed: whoever may change the product (its brand owner). */
    public function resolveReviewNote(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }
}
