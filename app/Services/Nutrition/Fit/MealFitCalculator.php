<?php

namespace App\Services\Nutrition\Fit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Enums\Allergen;

/**
 * Judges one meal against one person's criteria (pure: no database). The rules agreed with
 * the owner for phase 3 (1a 2a 3a):
 *  - each criterion is compared with its share of one meal (meal_share of a day value, or the
 *    meal value itself for a meal limit);
 *  - every upper limit counts, and so do the energy, carbohydrate and fat targets
 *    (fit_target_limits): the level comes from the largest overshoot (fit_tolerance);
 *  - being clearly under a minimum or the protein target (by more than the moderate
 *    tolerance) is a note, never a lower level;
 *  - a meal holding one of the person's allergens is "not for you now"; when an ingredient's
 *    allergens were not checked yet, people with allergies are told so;
 *  - a value the meal lacks is left out and named, never guessed;
 *  - the suitable portion is the largest part of the serving within every counted limit.
 */
class MealFitCalculator
{
    private const MAX_REASONS = 3;

    /** Below this share of the serving, a smaller portion is not offered. */
    private const MIN_PORTION_SHARE = 0.4;

    /** @param  array{fit_tolerance: array{suits: int, moderate: int, unfit: int}, fit_target_limits: list<string>}  $rules  config('nutrition_criteria') */
    public function __construct(private readonly array $rules) {}

    public function judge(CriteriaSheet $sheet, MealFacts $meal): MealFit
    {
        $overs = [];   // [criterion, amount, limit, percent over]
        $unders = [];  // notes: below a minimum or a target
        $missing = [];
        $factor = 1.0; // the share of the serving within every counted limit

        foreach ($sheet->criteria as $criterion) {
            $amount = $meal->amount($criterion->nutrient);
            $limit = $criterion->perMeal($sheet->mealShare);

            if ($amount === null) {
                if ($this->counts($criterion)) {
                    $missing[$criterion->nutrient] = $this->nutrientName($criterion);
                }

                continue;
            }
            if ($limit <= 0) {
                continue;
            }

            if ($this->counts($criterion)) {
                if ($amount > 0) {
                    $factor = min($factor, $limit / $amount);
                }
                // To a tenth, so "exactly 10% over" is not lost to a float's last digit.
                $over = round(($amount / $limit - 1) * 100, 1);
                if ($over > 0) {
                    $overs[] = [$criterion, $amount, $limit, $over];
                }
            } elseif ($amount < $limit * (1 - $this->rules['fit_tolerance']['moderate'] / 100)
                && in_array($criterion->bound, ['min', 'target'], true)) {
                // Clearly short only (under 75% of a meal's share): a small gap is not worth a note.
                $unders[] = [$criterion, $amount, $limit];
            }
        }

        usort($overs, fn (array $a, array $b) => $b[3] <=> $a[3]);

        $found = array_values(array_intersect($sheet->allergens, $meal->allergens));
        $level = $found ? 1 : $this->level($overs[0][3] ?? 0.0);

        $reasons = [
            ...array_map(fn (string $a) => 'فيها '.(Allergen::tryFrom($a)?->label() ?? $a).'، ولا يناسبك بحسب حالتك أو حساسيتك.', $found),
            ...($sheet->allergens && ! $meal->allergensChecked && ! $found
                ? ['لم نتحقق بعد من مسبّبات الحساسية في كل مكوّناتها، فتأكد منها مع المطعم.'] : []),
            ...array_map(fn (array $o) => $this->overReason(...$o), $overs),
        ];
        if (! $overs && ! $found) {
            $reasons[] = $this->energyReason($sheet, $meal);
        }

        $notes = [
            ...array_map(fn (array $u) => $this->underNote(...$u), $unders),
            ...($missing ? ['لا تتوفر قيمة '.implode(' و', array_unique($missing)).' لهذه الوجبة، فلم تدخل في التقييم.'] : []),
        ];

        return new MealFit(
            level: $level,
            reasons: array_slice(array_values(array_filter($reasons)), 0, self::MAX_REASONS),
            share: $this->share($sheet, $meal),
            portion: $found ? null : $this->portion($meal, $factor),
            notes: $notes,
            template: $sheet->template['label'],
        );
    }

    /** Upper limits always; targets only when listed (energy, carbohydrate, fat). */
    private function counts(CriterionValue $criterion): bool
    {
        return $criterion->bound === 'max'
            || ($criterion->bound === 'target' && in_array($criterion->key, $this->rules['fit_target_limits'], true));
    }

    private function level(float $over): int
    {
        $t = $this->rules['fit_tolerance'];

        return match (true) {
            $over <= $t['suits'] => 4,
            $over <= $t['moderate'] => 3,
            $over <= $t['unfit'] => 2,
            default => 1,
        };
    }

    private function overReason(CriterionValue $criterion, float $amount, float $limit, float $over): string
    {
        $share = $criterion->scope === 'meal' ? 'حدّك للوجبة' : 'حصة وجبة من احتياجك';

        return sprintf('%s فيها %s %s، و%s %s %s، أي أكثر بـ%d%%.',
            $criterion->label, self::number($amount), $criterion->unit,
            $share, self::number($limit), $criterion->unit, (int) round($over));
    }

    private function underNote(CriterionValue $criterion, float $amount, float $limit): string
    {
        return sprintf('%s فيها %s %s، أقل من حصة وجبة (%s %s). كمّلها في وجبة أخرى من يومك.',
            $criterion->label, self::number($amount), $criterion->unit, self::number($limit), $criterion->unit);
    }

    private function energyReason(CriteriaSheet $sheet, MealFacts $meal): ?string
    {
        $kcal = $meal->amount('energy_kcal');
        $day = $sheet->get('energy_kcal')?->value;

        return $kcal !== null && $day
            ? sprintf('سعراتها %s، أي %d%% من احتياجك اليومي، ضمن حصة وجبة واحدة، وكل حدودك محترمة.', self::number($kcal), (int) round($kcal / $day * 100))
            : 'كل حدودك محترمة في هذه الوجبة.';
    }

    /** @return array{energy: ?int, protein: ?int, carbs: ?int, fat: ?int} */
    private function share(CriteriaSheet $sheet, MealFacts $meal): array
    {
        $percent = function (string $key, string $nutrient) use ($sheet, $meal): ?int {
            $day = $sheet->get($key)?->value;
            $amount = $meal->amount($nutrient);

            return $day && $amount !== null ? (int) round($amount / $day * 100) : null;
        };

        return [
            'energy' => $percent('energy_kcal', 'energy_kcal'),
            'protein' => $percent('protein_g', 'protein_g'),
            'carbs' => $percent('carbohydrate_g', 'carbohydrate_g'),
            'fat' => $percent('fat_g', 'fat_g'),
        ];
    }

    /** @return array{grams: int, kcal: ?int, whole: bool}|null */
    private function portion(MealFacts $meal, float $factor): ?array
    {
        $kcal = $meal->amount('energy_kcal');

        if ($factor >= 1) {
            return ['grams' => (int) round($meal->portionGrams()), 'kcal' => $kcal !== null ? (int) round($kcal) : null, 'whole' => true];
        }
        if ($factor < self::MIN_PORTION_SHARE) {
            return null;
        }

        // Down to whole tens of grams, so the portion stays within the limits.
        $grams = (int) (floor($meal->portionGrams() * $factor / 10) * 10);
        $scale = $grams / $meal->portionGrams();

        return ['grams' => $grams, 'kcal' => $kcal !== null ? (int) round($kcal * $scale) : null, 'whole' => false];
    }

    /** The nutrient's plain name for "no value" notes (the meal limit shares it with the day value). */
    private function nutrientName(CriterionValue $criterion): string
    {
        return trim(str_replace('الوجبة', '', $criterion->label));
    }

    /** 2.4 under 10, whole numbers above (with thousands separators). */
    public static function number(float $value): string
    {
        return $value < 10 ? rtrim(rtrim(number_format($value, 1), '0'), '.') : number_format(round($value));
    }
}
