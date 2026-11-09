<?php

namespace App\Services\Site;

use App\Data\Nutrition\MealFacts;
use App\Models\User;
use App\Services\Nutrition\Fit\MealFitService;

/**
 * A brand's menu ready for a page: its sections of dish cards, each with the visitor's fit
 * badge, and the products still under review. Shared by a restaurant's page and the platform
 * kitchen's ("المطبخ السوري").
 */
class MenuView
{
    public function __construct(
        private readonly RestaurantDirectory $restaurants,
        private readonly MealFitService $fits,
    ) {}

    /** @return array{sections: list<array{id: ?int, name: ?string, dishes: list<array>}>, pending: list<array>, count: int} */
    public function for(int $brandId, ?User $user): array
    {
        $menu = $this->restaurants->menu($brandId);
        $verdicts = $this->fits->judgeAll($user, $menu['meals']);

        return [
            'sections' => array_map(fn (array $section) => [
                'id' => $section['id'],
                'name' => $section['name'],
                'dishes' => array_map(fn (MealFacts $m) => $m->card($verdicts[$m->id] ?? null), $section['meals']),
            ], $menu['sections']),
            'pending' => $menu['pending'],
            'count' => count($menu['meals']),
        ];
    }
}
