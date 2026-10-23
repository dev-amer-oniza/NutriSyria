<?php

namespace App\Http\Controllers\Site;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Services\Site\MealCatalog;
use App\Services\Site\RestaurantDirectory;
use Illuminate\Contracts\View\View;

/** "For restaurants": why and how a restaurant joins NutriSyria. */
class PartnersController extends Controller
{
    /** Live counts are shown only once they say something (the owner's decision 2a). */
    private const STATS_FROM_RESTAURANTS = 5;

    public function __invoke(RestaurantDirectory $restaurants, MealCatalog $meals): View
    {
        $all = $restaurants->all();

        return view('site.pages.partners', [
            'joinUrl' => route('site.register', ['type' => AccountType::BrandOwner->value]),
            // A real restaurant page to show, the one with the most approved meals.
            'example' => ($all[0] ?? null)?->mealCount ? $all[0] : null,
            'stats' => count($all) >= self::STATS_FROM_RESTAURANTS
                ? ['restaurants' => count($all), 'meals' => count($meals->meals())]
                : null,
        ]);
    }
}
