<?php

namespace App\Services\Nutrition;

/**
 * Pure calculation: no database, no Eloquent. Takes already-resolved numbers in, returns a
 * result out. This mirrors BundleValidator's split from BundleImporter — the math stays
 * testable without a database, and this same class serves BOTH brand product recipes and
 * (later) general dish recipes, since neither "Product" nor "Recipe" is known here at all.
 */
final class RecipeCalculator
{
    /**
     * @param  list<array{
     *     label: string,
     *     grams: ?float,
     *     problem?: ?string,
     *     is_added_after_cooking: bool,
     *     nutrients: array<int, array{amount: float, unit: string}>,
     * }>  $ingredients  one entry per recipe line, nutrients keyed by nutrient_id,
     *     amount is the source's amount_per_100g (not yet scaled by grams). grams is null when
     *     the line could not be converted to grams; problem says why
     * @param  list<array{id: int, code: string, name_ar: string, unit: string, is_core: bool, display_order: int}>  $nutrientDefs
     *     every nutrient to report on, even ones no ingredient has a value for
     */
    public function calculate(
        array $ingredients,
        array $nutrientDefs,
        ?float $weightBeforeCookingG,
        ?float $weightAfterCookingG,
        int $servings = 1,
    ): RecipeAnalysisResult {
        $gramsBeforeCooking = 0.0;   // ingredients present before cooking starts
        $gramsTotal = 0.0;           // every ingredient, including those added after cooking
        $unconverted = [];           // lines with unknown grams: reported, never counted as zero

        foreach ($ingredients as $line) {
            if ($line['grams'] === null) {
                $unconverted[] = ['label' => $line['label'], 'problem' => $line['problem'] ?? null];

                continue;
            }
            $gramsTotal += $line['grams'];
            if (! $line['is_added_after_cooking']) {
                $gramsBeforeCooking += $line['grams'];
            }
        }

        $totals = [];        // nutrient_id => ['amount' => float|null, 'missing_from' => string[]]
        foreach ($nutrientDefs as $def) {
            $sum = 0.0;
            $hasAnyValue = false;
            $missingFrom = [];

            foreach ($ingredients as $line) {
                if ($line['grams'] === null || $line['grams'] <= 0) {
                    continue;
                }
                $value = $line['nutrients'][$def['id']] ?? null;
                if ($value === null) {
                    $missingFrom[] = $line['label'];

                    continue;
                }
                $hasAnyValue = true;
                $sum += ($line['grams'] / 100) * $value['amount'];
            }

            $totals[$def['id']] = [
                'nutrient' => $def,
                // No ingredient reported this nutrient at all: unknown, not zero.
                'amount' => $hasAnyValue ? round($sum, 6) : null,
                'missing_from' => $missingFrom,
            ];
        }

        // Sanity check: do the ingredient weights roughly match the stated pre-cook weight?
        $weightWarning = null;
        if ($weightBeforeCookingG !== null && $weightBeforeCookingG > 0) {
            $diffPct = (($gramsBeforeCooking - $weightBeforeCookingG) / $weightBeforeCookingG) * 100;
            if (abs($diffPct) >= 10) {
                $weightWarning = sprintf(
                    'مجموع وزن المكونات قبل الطبخ (%.1f غ) يختلف عن الوزن المُدخَل (%.1f غ) بنسبة %.1f%%',
                    $gramsBeforeCooking,
                    $weightBeforeCookingG,
                    $diffPct,
                );
            }
        }

        return new RecipeAnalysisResult(
            totals: $totals,
            gramsBeforeCooking: $gramsBeforeCooking,
            gramsTotal: $gramsTotal,
            weightBeforeCookingG: $weightBeforeCookingG,
            weightAfterCookingG: $weightAfterCookingG,
            servings: max(1, $servings),
            weightWarning: $weightWarning,
            unconvertedIngredients: $unconverted,
        );
    }
}
