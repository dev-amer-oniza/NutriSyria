<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Site\FitExample;
use App\Services\Site\MealCatalog;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /** Search chips when there are not enough approved meals to suggest. */
    private const FALLBACK_SUGGESTIONS = ['مجدّرة', 'فتة', 'شاورما دجاج', 'فول مدمّس', 'تبولة'];

    public function __invoke(MealCatalog $meals, FitExample $example): View
    {
        $dishes = $meals->latest(8);

        return view('site.pages.home', [
            'dishes' => $dishes,
            'suggestions' => $this->suggestions($dishes),
            'featured' => $example->meal(),
        ]);
    }

    /** @param list<array{name: string}> $dishes */
    private function suggestions(array $dishes): array
    {
        $names = array_values(array_unique(array_column($dishes, 'name')));

        return count($names) >= 3 ? array_slice($names, 0, 5) : self::FALLBACK_SUGGESTIONS;
    }
}
