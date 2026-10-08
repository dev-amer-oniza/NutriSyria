<?php

namespace App\Http\Controllers;


use App\Http\Resources\ItemResource;
use App\Http\Resources\MenuItemAnalysisResource;
use App\Http\Resources\RestaurantMenuResource;
use App\Services\CartService;
use App\Services\RestaurantMenuService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function __construct(
        private readonly RestaurantMenuService $menuService,
        private readonly CartService $cartService
    ) {
    }


    public function menu(int $restaurant, Request $request): JsonResponse
    {
        try {
            $latitude = $request->has('latitude') ? $request->float('latitude') : null;
            $longitude = $request->has('longitude') ? $request->float('longitude') : null;
            $data = $this->menuService->getMenuData($restaurant,$latitude, $longitude );
            $data['details'] = false;
        } catch (ModelNotFoundException) {
            return self::error('Restaurant not found.', 404);
        } catch (\Throwable $e) {
            report($e);
            return self::error('Could not load the menu. Please try again.', 500);
        }

        return self::success(
            new RestaurantMenuResource($data),
            'Menu retrieved successfully.'
        );
    }

    public function getItem(int $restaurant, int $menu_item)
    {
        try {
            $data = $this->menuService->getItem($restaurant, $menu_item);
            $data['details'] = true;
        } catch (ModelNotFoundException $e) {
            return self::error($e->getMessage(), 404);
        } catch (\Throwable $e) {
            report($e);
            return self::error('Could not load the item. Please try again.', 500);
        }
        return self::success(new ItemResource($data), 'Item retrieved successfully.');
    }

    public function showMealAnalysis(int $restaurantId, int $menuItemId){
        $analysis = $this->menuService->showAnalysis($restaurantId ,$menuItemId );
        if(!  $analysis){
            return self::error('This menu item has not been analyzed yet.',404);
        }
        return self::success(MenuItemAnalysisResource::make($analysis));
    }

}

