<?php

namespace App\Http\Controllers\Site;

use App\Enums\BrandKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurants\RestaurantSearchRequest;
use App\Services\Site\MenuView;
use App\Services\Site\RestaurantDirectory;
use App\Support\Site\Pages;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The restaurants and brands on the site, and one of them with its details and menu. */
class RestaurantController extends Controller
{
    public function index(RestaurantSearchRequest $request, RestaurantDirectory $restaurants): View
    {
        return view('site.pages.restaurants.index', [
            'page' => Pages::of($restaurants->all($request->search(), $request->deliveryOnly(), kind: $request->kind()), $request),
            'search' => $request->search(),
            'deliveryOnly' => $request->deliveryOnly(),
            'kind' => $request->kind(),
            'kinds' => BrandKind::cases(),
        ]);
    }

    public function show(Request $request, int $restaurant, RestaurantDirectory $restaurants, MenuView $menus): View
    {
        $facts = $restaurants->find($restaurant) ?? abort(404);
        $menu = $menus->for($facts->id, $request->user());

        return view('site.pages.restaurants.show', [
            'restaurant' => $facts,
            'sections' => $menu['sections'],
            'pending' => $menu['pending'],
        ]);
    }
}
