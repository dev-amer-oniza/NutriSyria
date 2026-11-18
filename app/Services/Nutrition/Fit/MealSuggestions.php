<?php

namespace App\Services\Nutrition\Fit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Data\Nutrition\MealSuggestion;

/**
 * "ماذا تستطيع أن تفعل" (decisions 1a 2a): changes tried on a meal
 * and judged again in full, kept only when they help. Pure.
 *
 *  - What goes on top (after cooking) is tried without it; for a restaurant, that is the only
 *    change, the one a diner can ask for (decision 10a).
 *  - In the kitchen, the three ingredients giving most of a nutrient that went over are tried
 *    at three quarters, two thirds and half, and the smallest cut that helps is offered.
 *  - A change helps when the level goes up.
 */
final class MealSuggestions
{
    public const MAX = 2;

    /** What is kept of an ingredient, the smallest cut first. */
    public const KEEP = [0.75, 2 / 3, 0.5];

    public function __construct(private readonly MealFitCalculator $calculator) {}

    /** @return list<MealSuggestion> */
    public function for(CriteriaSheet $sheet, MealFacts $meal, MealFit $fit): array
    {
        $targets = array_values(array_unique(array_values($fit->causes)));
        // Nothing went over, or an allergen decides: no change of amounts helps.
        if ($meal->lines === [] || $targets === [] || array_intersect($sheet->allergens, $meal->allergens) !== []) {
            return [];
        }

        // Each option: one ingredient's cuts, the smallest first.
        $options = [];
        foreach ($meal->lines as $line) {
            if ($line['after']) {
                $options[] = [[$line, 0.0]];
            }
        }
        if ($meal->platform) {
            foreach ($targets as $nutrient) {
                foreach ($this->largest($meal, $nutrient) as $line) {
                    $options[$line['label']] ??= array_map(fn (float $keep) => [$line, $keep], self::KEEP);
                }
            }
        }
        $options = array_values($options);

        $found = [];
        foreach ($options as $cuts) {
            $found[] = $this->first($sheet, $meal, $fit, $targets, array_map(fn (array $cut) => [$cut], $cuts));
        }
        // When no one change helps, two together (decision 2a: the musabbaha's oil and tahini).
        if (array_filter($found) === []) {
            foreach ($options as $a => $first) {
                foreach (array_slice($options, $a + 1) as $second) {
                    $pairs = [];
                    foreach ($first as $one) {
                        foreach ($second as $two) {
                            $pairs[] = [$one, $two];
                        }
                    }
                    // The smallest cut in all first.
                    usort($pairs, fn (array $x, array $y) => ($y[0][1] + $y[1][1]) <=> ($x[0][1] + $x[1][1]));
                    $found[] = $this->first($sheet, $meal, $fit, $targets, $pairs);
                }
            }
        }

        $found = array_values(array_filter($found));
        // What raises the level most first; then one change before two, leaving out what goes on
        // top before cutting the dish itself, and the fewest grams off the plate.
        usort($found, fn (MealSuggestion $a, MealSuggestion $b) => [$b->level, count($a->changes), $b->onTopOnly(), $a->cutGrams]
            <=> [$a->level, count($b->changes), $a->onTopOnly(), $b->cutGrams]);

        return array_slice($found, 0, self::MAX);
    }

    /**
     * The first of these changes (each a list of [line, keep]) that helps.
     *
     * @param  list<string>  $targets
     * @param  list<list<array{0: array, 1: float}>>  $tries
     */
    private function first(CriteriaSheet $sheet, MealFacts $meal, MealFit $fit, array $targets, array $tries): ?MealSuggestion
    {
        foreach ($tries as $cuts) {
            if ($suggestion = $this->try($sheet, $meal, $fit, $cuts, $targets)) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * @param  list<array{0: array, 1: float}>  $cuts
     * @param  list<string>  $targets
     */
    private function try(CriteriaSheet $sheet, MealFacts $meal, MealFit $fit, array $cuts, array $targets): ?MealSuggestion
    {
        $changed = $meal;
        foreach ($cuts as [$line, $keep]) {
            $changed = $changed->without($line, $keep);
        }
        $after = $this->calculator->judge($sheet, $changed);
        if ($after->level <= $fit->level) {
            return null;
        }

        // The nutrient the change is about: the one it cuts most among those that went over.
        $nutrient = null;
        $most = 0.0;
        foreach ($targets as $code) {
            $cut = array_sum(array_map(fn (array $c) => ($c[0]['nutrients'][$code] ?? 0) * (1 - $c[1]), $cuts));
            if ($cut > $most) {
                [$nutrient, $most] = [$code, $cut];
            }
        }

        return new MealSuggestion(
            changes: array_map(fn (array $c) => ['ingredient' => $c[0]['label'], 'after' => $c[0]['after'], 'keep' => $c[1]], $cuts),
            level: $after->level,
            nutrient: $meal->platform ? $nutrient : null,
            amount: $meal->platform && $nutrient ? $changed->amount($nutrient) : null,
            cutGrams: array_sum(array_map(fn (array $c) => ($c[0]['grams'] ?? 0) * (1 - $c[1]), $cuts)),
        );
    }

    /** @return list<array> the three ingredients (not on top) giving most of a nutrient */
    private function largest(MealFacts $meal, string $nutrient): array
    {
        $lines = array_filter($meal->lines, fn (array $line) => ! $line['after'] && ($line['nutrients'][$nutrient] ?? 0) > 0);
        usort($lines, fn (array $a, array $b) => $b['nutrients'][$nutrient] <=> $a['nutrients'][$nutrient]);

        return array_slice($lines, 0, 3);
    }
}
