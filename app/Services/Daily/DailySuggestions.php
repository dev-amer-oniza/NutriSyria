<?php

namespace App\Services\Daily;

use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Enums\MealType;

/**
 * "شو بيناسبك على العشا؟" (pure): meals that suit the person (very well or in moderation,
 * decision 1a: the verdict stays per meal) and fit in what is left of today's calories.
 * This meal's own dishes come first (by their menu section), then dishes with no meal named;
 * a dish of another meal is left out. Within each, the better verdict, then the newest.
 */
final class DailySuggestions
{
    private const SUITING = 3;

    /**
     * @param  list<MealFacts>  $meals  newest approval first
     * @param  array<int, MealFit>  $verdicts  by meal id
     * @param  callable(MealFacts): ?MealType  $periodOf
     * @return list<MealFacts>
     */
    public static function pick(array $meals, array $verdicts, callable $periodOf, MealType $type, float $leftKcal, int $limit): array
    {
        $ranked = [];
        foreach ($meals as $order => $meal) {
            $fit = $verdicts[$meal->id] ?? null;
            $kcal = $meal->nutrients['energy_kcal'] ?? null;
            // A plate is needed to say what one meal brings; per-100 g meals are left out.
            if (! $fit || $fit->level < self::SUITING || $meal->servingGrams === null || $kcal === null || $kcal > $leftKcal) {
                continue;
            }

            $period = $periodOf($meal);
            if ($period !== null && $period !== $type && $type !== MealType::Snack) {
                continue;
            }

            $ranked[] = [$period === $type ? 0 : 1, -$fit->level, $order, $meal];
        }

        usort($ranked, fn ($a, $b) => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(fn ($r) => $r[3], array_slice($ranked, 0, $limit));
    }
}
