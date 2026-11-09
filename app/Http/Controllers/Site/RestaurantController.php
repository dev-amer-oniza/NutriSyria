<?php

namespace App\Http\Controllers\Site;

use App\Data\Nutrition\MealFacts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\RestaurantSearchRequest;
use App\Services\Nutrition\Fit\MealFitService;
use App\Services\Site\RestaurantDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The restaurants on the site, and one restaurant with its details and menu. */
class RestaurantController extends Controller
{
    public function index(RestaurantSearchRequest $request, RestaurantDirectory $restaurants): View
    {
        return view('site.pages.restaurants.index', [
            'restaurants' => $restaurants->all($request->search(), $request->deliveryOnly()),
            'search' => $request->search(),
            'deliveryOnly' => $request->deliveryOnly(),
        ]);
    }

    public function show(Request $request, int $restaurant, RestaurantDirectory $restaurants, MealFitService $fits): View
    {
        $facts = $restaurants->find($restaurant) ?? abort(404);
        $menu = $restaurants->menu($facts->id);
        $verdicts = $fits->judgeAll($request->user(), $menu['meals']);

        return view('site.pages.restaurants.show', [
            'restaurant' => $facts,
            'sections' => array_map(fn (array $section) => [
                'id' => $section['id'],
                'name' => $section['name'],
                'dishes' => array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $section['meals']),
            ], $menu['sections']),
            'pending' => $menu['pending'],
        ]);
    }
}
