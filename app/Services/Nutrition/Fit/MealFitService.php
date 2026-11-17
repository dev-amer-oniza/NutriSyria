<?php

namespace App\Services\Nutrition\Fit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Data\Nutrition\MealSuggestion;
use App\Models\User;
use App\Services\Nutrition\Criteria\PersonalCriteria;

/**
 * A meal's verdict for the signed-in person: their criteria sheet (PersonalCriteria, read
 * once per request) judged by MealFitCalculator. Null for a guest, or while the profile is
 * incomplete or under the minimum age.
 */
class MealFitService
{
    /** @var array<int, CriteriaSheet|null> */
    private array $sheets = [];

    public function __construct(private readonly PersonalCriteria $criteria) {}

    public function sheetFor(?User $user): ?CriteriaSheet
    {
        if (! $user) {
            return null;
        }

        return $this->sheets[$user->getKey()] ??= $this->criteria->for($user);
    }

    public function judge(?User $user, MealFacts $meal): ?MealFit
    {
        $sheet = $this->sheetFor($user);

        return $sheet ? $this->calculator()->judge($sheet, $meal) : null;
    }

    /**
     * @param  list<MealFacts>  $meals
     * @return array<int, MealFit> by meal id (empty when there is no sheet)
     */
    public function judgeAll(?User $user, array $meals): array
    {
        $sheet = $this->sheetFor($user);
        $calculator = $this->calculator();

        return $sheet
            ? collect($meals)->mapWithKeys(fn (MealFacts $meal) => [$meal->id => $calculator->judge($sheet, $meal)])->all()
            : [];
    }

    /**
     * "ماذا تستطيع أن تفعل" for one meal page (step 6): changes judged again in full. A few more
     * judgements of the same meal, on its own page only, never for a list.
     *
     * @return list<MealSuggestion>
     */
    public function suggest(?User $user, MealFacts $meal, ?MealFit $fit): array
    {
        $sheet = $this->sheetFor($user);

        return $sheet && $fit ? (new MealSuggestions($this->calculator()))->for($sheet, $meal, $fit) : [];
    }

    private function calculator(): MealFitCalculator
    {
        return new MealFitCalculator(config('nutrition_criteria'));
    }
}
