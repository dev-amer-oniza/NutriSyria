<?php

namespace App\Http\Controllers;

use App\Http\Resources\HomeResources\CategoryResource;
use App\Http\Resources\HomeResources\RestaurantByCategoryResource;
use App\Http\Resources\HomeResources\RestaurantResource;
use App\Models\Category;
use App\Services\HomeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __construct(private HomeService $service)
    {
    }

    public function getHomeData(Request $request)
    {
        $latitude = $request->has('latitude') ? $request->float('latitude') : null;
        $longitude = $request->has('longitude') ? $request->float('longitude') : null;
        $radiusKm = $request->float('radiusKm', 5);


        $categories = Cache::remember('categories.active', now()->addHours(6), function () {
            return $this->service->getAllActiveCategoriesWithPhoto();
        });

        $randomRestaurants = $this->service->getRestaurant($latitude, $longitude);

        $data = [
            'categories' => CategoryResource::collection($categories),
            'restaurants' =>  RestaurantResource::collection($randomRestaurants),
        ];

        return $this->success($data, "all data fetched successfully");


    }

    public function RestaurantByCategory(Category $category, Request $request)
    {
        $latitude = $request->has('latitude') ? $request->float('latitude') : null;
        $longitude = $request->has('longitude') ? $request->float('longitude') : null;
        $radiusKm = $request->float('radiusKm', 10);
        $perPage = $request->integer('perPage', 8);

        $key = 'restaurant.by.category.' . $category->id
            . '.lat:' . $latitude
            . '.lng:' . $longitude
            . '.r:' . $radiusKm
            . '.p:' . $perPage;

        $restaurants = Cache::remember($key, now()->addHours(6), function () use ($category, $latitude, $longitude, $radiusKm, $perPage) {
            return $this->service->getRestaurantByCategory($category, $latitude, $longitude, $radiusKm, $perPage);
        });

        $data = [
            'restaurants' => RestaurantByCategoryResource::collection($restaurants),
        ];
        return $this->success($data, 'Restaurants retrieved successfully.');
    }

    public function categories()
    {
        $categories = $this->service->getAllActiveCategoriesWithoutPhoto();
        return $this->success($categories);
    }
}
