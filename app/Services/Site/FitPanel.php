<?php

namespace App\Services\Site;

use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;

/**
 * The home page's fit panel (sections/home/fit-panel): for a signed-in person with criteria,
 * the newest meal judged for them; otherwise the labelled example (FitExample).
 */
class FitPanel
{
    public function __construct(private readonly FitExample $example) {}

    /** @return array<string, mixed> */
    public function for(?MealFacts $meal, ?MealFit $fit): array
    {
        if (! $meal || ! $fit) {
            return $this->example->meal();
        }

        $round = fn (string $code) => (int) round($meal->amount($code) ?? 0);

        return [
            'name' => $meal->name,
            'brand' => $meal->brand,
            'portion' => (int) round($meal->portionGrams()),
            'kcal' => $round('energy_kcal'),
            'macros' => ['protein' => $round('protein_g'), 'carbs' => $round('carbohydrate_g'), 'fat' => $round('fat_g')],
            'fiber' => $round('fiber_g'),
            'verification' => $meal->verification,
            'ask' => 'مدى مناسبتها لك، من معاييرك أنت:',
            'people' => [[
                'label' => 'لك أنت',
                'who' => 'حسب قالبك: '.$fit->template.'.',
                'level' => $fit->level,
                'reasons' => $fit->reasons,
                'notes' => $fit->notes,
                'share' => $fit->share,
                'portion' => $fit->portion,
            ]],
            'source' => 'أحدث وجبة اعتمدها أخصائي التغذية، مقيّمة على معاييرك.',
            'link' => route('site.meals.show', $meal->id),
            'link_label' => 'صفحة الوجبة',
        ];
    }
}
