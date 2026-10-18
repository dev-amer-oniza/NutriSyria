<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\ProductRecipeIngredient;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/** A recipe line belongs to its product: whoever may see / edit the product may see / edit its lines. */
class ProductRecipeIngredientPolicy
{
    use DeniesByDefault;

    public function __construct(private readonly ProductPolicy $products = new ProductPolicy())
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->products->viewAny($user);
    }

    public function view(User $user, ProductRecipeIngredient $line): bool
    {
        $product = self::productOf($line);

        return $product !== null && $this->products->view($user, $product);
    }

    public function create(User $user): bool
    {
        // Lines are added from a product page, which itself needs update rights on that product.
        return $this->products->create($user);
    }

    public function update(User $user, ProductRecipeIngredient $line): bool
    {
        $product = self::productOf($line);

        return $product !== null && $this->products->update($user, $product);
    }

    public function delete(User $user, ProductRecipeIngredient $line): bool
    {
        return $this->update($user, $line);
    }

    public function deleteAny(User $user): bool
    {
        return $this->products->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->products->create($user);
    }

    private static function productOf(ProductRecipeIngredient $line): ?Product
    {
        return $line->productRecipe?->product;
    }
}
