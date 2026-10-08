<?php

namespace App\Http\Controllers;

use App\Exceptions\CartTenantMismatchException;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\SyncCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Requests\UpdateItemInCart;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    public function index()
    {
        $cart = $this->cartService->getGroupedCart();
        return self::success($cart);
    }

    public function add(AddToCartRequest $request)
    {
        $result = $this->cartService->addItem(
            data: $request->validated()
        );
        return self::success($result, 'Item added to cart.');

    }

    public function editItem(AddToCartRequest $request,$cartId)
    {
        
        $result = $this->cartService->editItem(
            $request->validated(),
            $cartId
        );
        return self::success($result, 'Item updated successfully');

    }

    public function cartByRestaurant($restaurantId)
    {
        $items = $this->cartService->getCartItemsByRestaurant($restaurantId);
        return self::success($items);
    }

    public function clear()
    {
        $this->cartService->clearCart();
        return $this->success(null, 'your cart cleared successfully.');
    }

    public function destroy($restaurantId)
    {
        $this->cartService->removeCart($restaurantId);
        return $this->success(null, 'cart removed successfully.');
    }

    public function removeItem($itemId)
    {
        $this->cartService->deleteItemFromCart($itemId);
        return self::success(null, 'تم حذف العنصر من السلة');
    }

    public function getItemForEdit($cartId)
    {
        $selected = $this->cartService->getItemForEdit($cartId);

        return self::success($selected);
        
    }

    public function editeItemQuantity(SyncCartRequest $request)
    {
        $this->cartService->editeQuantity($request->validated());
        return self::success(null, 'item quantity edite successfuly');
    }

    public function restaurantInfo($restaurantId)
    {
        $res = $this->cartService->getRestaurantInfo($restaurantId);
        return self::success($res);
    }
}
