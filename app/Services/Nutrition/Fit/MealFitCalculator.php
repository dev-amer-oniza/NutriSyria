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
 *    meal value itself for a meal limit); with a nutritionist's plan, with the plan's lunch:
 *    its own calories and grams, and its share of the day for the rest (plan phase 3, 1a);
 *  - every upper limit counts, and so do the energy, carbohydrate and fat targets
 *    (fit_target_limits): the level comes from the largest overshoot (fit_tolerance);
 *  - being clearly under a minimum (by more than the moderate tolerance) is a note, never a
 *    lower level; a target is not a need, so being under it says nothing (protein is measured
 *    against its RDA, protein_min_g), and each nutrient gets one note at most;
 *  - an 'info_only' criterion (total sugars: its reference is for free sugars) never judges;
 *  - a meal holding one of the person's allergens is "not for you now"; when an ingredient's
 *    allergens were not checked yet, people with allergies are told so;
 *  - a value the meal lacks is left out and named, never guessed;
 *  - the suitable portion is the largest part of the serving within every counted limit, said
 *    as a plate: whole, ¾, ½ or ¼, rounded down so it never passes a limit (stage 3, 1a);
 *  - one short reason without numbers leads (2a), with "من أين هذا الحد؟": how its limit was
 *    reached, step by step, and the references (3a). The rest stays under "تفاصيل أكثر".
 */
class MealFitCalculator
{
    private const MAX_REASONS = 3;

    /** The plates offered, largest first; under the last one, no portion is offered. */
    public const PLATES = [1.0, 0.75, 0.5, 0.25];

    /** Below this share of the serving, a smaller portion is not offered. */
    public const MIN_PORTION_SHARE = 0.25;

    /** @param  array{fit_tolerance: array{suits: int, moderate: int, unfit: int}, fit_target_limits: list<string>, fit_near_pct?: int, criteria?: array<string, array<string, mixed>>}  $rules  config('nutrition_criteria') */
    public function __construct(private readonly array $rules) {}

    public function judge(CriteriaSheet $sheet, MealFacts $meal): MealFit
    {
        $overs = [];   // by nutrient: [criterion, amount, limit, percent over, level]
        $unders = [];  // notes: below a minimum, by nutrient
        $missing = [];
        $factor = 1.0; // the share of the serving within every counted limit

        foreach ($sheet->criteria as $criterion) {
            $amount = $meal->amount($criterion->nutrient);
            $limit = $sheet->perMeal($criterion);
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
                $level = $this->level($over, $criterion->kind);
                $held = $overs[$criterion->nutrient] ?? null;
                // One reason a nutrient: the limit that drops the verdict most, then the larger overshoot.
                if ($over > 0 && (! $held || $level < $held[4] || ($level === $held[4] && $over > $held[3]))) {
                    $overs[$criterion->nutrient] = [$criterion, $amount, $limit, $over, $level];
                }
            } elseif ($criterion->bound === 'min'
                && $amount < $limit * (1 - $this->rules['fit_tolerance']['moderate'] / 100)
                && $limit > ($unders[$criterion->nutrient][2] ?? 0)) {
                // Clearly short only (under 75% of a meal's share): a small gap is not worth a note.
                // One note a nutrient, against its highest minimum.
                $unders[$criterion->nutrient] = [$criterion, $amount, $limit];
            }
        }

        $overs = array_values($overs);
        usort($overs, fn (array $a, array $b) => [$a[4], -$a[3]] <=> [$b[4], -$b[3]]);

        $found = array_values(array_intersect($sheet->allergens, $meal->allergens));
        $level = $found ? 1 : ($overs[0][4] ?? 4);

        // [reason, the nutrient it is about]: "من أين الدهون؟" sits under an over-limit reason (step 4).
        $reasons = [
            ...array_map(fn (string $a) => ['فيها '.(Allergen::tryFrom($a)?->label() ?? $a).'، وكتبتَ في ملفك أنه يسبّب لك حساسية.', null], $found),
            ...($sheet->allergens && ! $meal->allergensChecked && ! $found
                ? [['لم نتحقق بعد من مسبّبات الحساسية في كل مكوّناتها، فتأكد منها مع المطعم.', null]] : []),
            ...array_map(fn (array $o) => [$this->overReason($sheet, ...array_slice($o, 0, 4)), $o[0]->nutrient], $overs),
        ];
        if (! $overs && ! $found) {
            $reasons[] = [$this->energyReason($sheet, $meal), null];
        }
        $reasons = array_slice(array_values(array_filter($reasons, fn (array $r) => (bool) $r[0])), 0, self::MAX_REASONS);

        [$headline, $why] = $this->headline($sheet, $meal, $found, $overs);

        $notes = [
            ...array_map(fn (array $u) => $this->underNote($sheet, ...$u), array_values($unders)),
            ...($missing ? ['لا تتوفر قيمة '.implode(' و', array_unique($missing)).' لهذه الوجبة، فلم تدخل في التقييم.'] : []),
        ];

        return new MealFit(
            level: $level,
            reasons: array_column($reasons, 0),
            causes: array_filter(array_column($reasons, 1)),
            share: $this->share($sheet, $meal),
            portion: $found ? null : $this->portion($meal, $factor),
            notes: $notes,
            template: $sheet->template['label'],
            basis: $sheet->meal ? sprintf('غدائك في خطة أخصائيّك (%s%s سعرة)', $sheet->meal['time'] ? $sheet->meal['time'].'، ' : '', number_format($sheet->meal['kcal'])) : null,
            headline: $headline,
            why: $why,
        );
    }

    /**
     * The one reason that leads, in plain words, and how its limit was reached.
     *
     * @param  list<string>  $found  the person's allergens in the meal
     * @param  list<array{0: CriterionValue, 1: float, 2: float, 3: float, 4: int}>  $overs  sorted, worst first
     * @return array{0: string, 1: ?array{steps: list<string>, refs: list<string>}}
     */
    private function headline(CriteriaSheet $sheet, MealFacts $meal, array $found, array $overs): array
    {
        if ($found) {
            $name = Allergen::tryFrom($found[0])?->label() ?? $found[0];

            return ["فيها {$name}، وعندك حساسية منه.", ['steps' => ["كتبتَ في ملفك أن {$name} يسبّب لك حساسية، وأحد مكوّنات هذه الوجبة فيه {$name}."], 'refs' => []]];
        }

        [$criterion, $amount, $limit, $over, $level] = $overs[0] ?? [null, 0.0, 0.0, 0.0, 4];
        if ($criterion && $level < 4) {
            $than = match (true) {
                $criterion->scope === 'meal' && $criterion->source->setter() !== null => 'من الحد الذي حدّده لك '.$criterion->source->setter(),
                $sheet->meal !== null && $criterion->scope === 'day' => 'من غدائك في خطة أخصائيّك',
                default => 'من حصتك في الوجبة',
            };

            return [$this->nutrientName($criterion).' فيها أكثر '.$than.'.', $this->why($sheet, $criterion, $amount, $limit, $over)];
        }
        if ($sheet->allergens && ! $meal->allergensChecked) {
            return ['لم نتحقق بعد من مسبّبات الحساسية فيها، فاسأل المطعم عنها.', null];
        }
        if ($criterion) {
            return [$this->nutrientName($criterion).' فيها قريب من حصتك في الوجبة، وضمن حدودك.', $this->why($sheet, $criterion, $amount, $limit, $over)];
        }

        $energy = $sheet->get('energy_kcal');
        $kcal = $meal->amount('energy_kcal');

        return ['ضمن حدودك في هذه الوجبة.', $energy && $kcal !== null ? $this->why($sheet, $energy, $kcal, $sheet->perMeal($energy), 0.0) : null];
    }

    /**
     * "من أين هذا الحد؟": the day's value and its basis, the meal's share of it, and what the
     * meal holds; the references only for a value we work out (a nutritionist's is their own).
     *
     * @return array{steps: list<string>, refs: list<string>}
     */
    private function why(CriteriaSheet $sheet, CriterionValue $criterion, float $amount, float $limit, float $over): array
    {
        $n = fn (float $v) => self::number($v).' '.$criterion->unit;
        $name = $this->nutrientName($criterion);
        $own = $criterion->source->setter();

        $steps = $criterion->scope === 'meal'
            ? [sprintf('%s: حد للوجبة الواحدة %s، %s.', $name, $n($limit), $own ? 'حدّده لك '.$own : 'محسوب لك')]
            : [
                sprintf('%s في يومك %s: %s.', $name, $n($criterion->value), $own ? 'حدّده لك '.$own : $criterion->basis),
                $sheet->meal !== null
                    ? sprintf('غداؤك في خطة أخصائيّك له %s.', $n($limit))
                    : sprintf('الوجبة الواحدة %d%% من يومك، أي %s.', (int) round($sheet->mealShare * 100), $n($limit)),
            ];
        $steps[] = sprintf('في هذه الوجبة %s%s', $n($amount), $over >= 0.5 ? '، أي أكثر بـ'.(int) round($over).'%.' : '.');

        return [
            'steps' => $steps,
            'refs' => $own ? [] : array_values($this->rules['criteria'][$criterion->key]['refs'] ?? []),
        ];
    }

    /** Upper limits always; targets only when listed (energy, carbohydrate, fat); never an information. */
    private function counts(CriterionValue $criterion): bool
    {
        if ($this->rules['criteria'][$criterion->key]['info_only'] ?? false) {
            return false;
        }

        return $criterion->bound === 'max'
            || ($criterion->bound === 'target' && in_array($criterion->key, $this->rules['fit_target_limits'], true));
    }

    /** The level an overshoot gives; only a strict limit (a nutritionist's) may say "not for you now". */
    private function level(float $over, string $kind = 'strict'): int
    {
        $t = $this->rules['fit_tolerance'];

        $level = match (true) {
            $over <= $t['suits'] => 4,
            $over <= $t['moderate'] => 3,
            $over <= $t['unfit'] => 2,
            default => 1,
        };

        return $kind === 'strict' ? $level : max($level, 2);
    }

    private function overReason(CriteriaSheet $sheet, CriterionValue $criterion, float $amount, float $limit, float $over): string
    {
        $unit = $criterion->unit;
        $setter = $criterion->source->setter();
        $n = fn (float $v) => self::number($v).' '.$unit;

        // What the meal is held to, said plainly: a meal limit, the plan's lunch, or a share of the day
        // with the day's figure beside it, never the day's figure alone.
        [$what, $within] = match (true) {
            $criterion->scope === 'meal' && $setter !== null => ['والحد الذي حدّده لك '.$setter.' '.$n($limit).' للوجبة', 'الحد الذي حدّده لك '.$setter.' للوجبة ('.$n($limit).')'],
            $criterion->scope === 'meal' => ['وحدّك للوجبة '.$n($limit), 'حدّك للوجبة ('.$n($limit).')'],
            $sheet->meal !== null => ['وغداؤك في خطتك '.$n($limit), 'غدائك في خطتك ('.$n($limit).')'],
            default => [
                'وحصة وجبة من '.($setter ? 'الحد الذي حدّده لك '.$setter : 'يومك').' '.$n($limit).' (من '.$n($criterion->value).' في اليوم)',
                'حصة وجبة من '.($setter ? 'الحد الذي حدّده لك '.$setter : 'يومك').' ('.$n($limit).' من '.$n($criterion->value).' في اليوم)',
            ],
        };

        // Just over (15.1 g against 15 g): "within", not "1% more" between two equal-looking numbers.
        if ($over < ($this->rules['fit_near_pct'] ?? 0)) {
            return sprintf('%s فيها %s، أي بحدود %s.', $criterion->label, $n($amount), $within);
        }

        return sprintf('%s فيها %s، %s، أي أكثر بـ%d%%.', $criterion->label, $n($amount), $what, (int) round($over));
    }

    private function underNote(CriteriaSheet $sheet, CriterionValue $criterion, float $amount, float $limit): string
    {
        // "البروتين (الحد الأدنى)" reads as the protein, measured against the least a person needs.
        $floor = str_contains($criterion->label, '(الحد الأدنى)');
        $share = $sheet->meal ? 'غدائك في خطتك' : 'حصة وجبة';

        return sprintf('%s فيها %s %s، أقل من %s (%s %s). كمّلها في وجبة أخرى من يومك.',
            trim(str_replace('(الحد الأدنى)', '', $criterion->label)), self::number($amount), $criterion->unit,
            $floor ? $share.' من الحد الأدنى لاحتياجك' : $share, self::number($limit), $criterion->unit);
    }

    private function energyReason(CriteriaSheet $sheet, MealFacts $meal): ?string
    {
        $kcal = $meal->amount('energy_kcal');
        $day = $sheet->get('energy_kcal')?->value;

        $within = $sheet->meal ? 'ضمن غدائك في خطتك' : 'ضمن حصة وجبة واحدة';

        return $kcal !== null && $day
            ? sprintf('سعراتها %s، أي %d%% من احتياجك اليومي، %s، وكل حدودك محترمة.', self::number($kcal), (int) round($kcal / $day * 100), $within)
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

    /**
     * The largest plate (whole, ¾, ½, ¼) within every counted limit: down to the quarter, so
     * it never passes one; null under a quarter. Without the serving's weight (values per 100 g)
     * there is no plate to speak of: 'per100g' says so, and the grams are what to show.
     *
     * @return array{fraction: float, grams: int, kcal: ?int, whole: bool, per100g: bool}|null
     */
    private function portion(MealFacts $meal, float $factor): ?array
    {
        $kcal = $meal->amount('energy_kcal');

        foreach (self::PLATES as $fraction) {
            if ($factor >= $fraction) {
                return [
                    'fraction' => $fraction,
                    'grams' => (int) round($meal->portionGrams() * $fraction),
                    'kcal' => $kcal !== null ? (int) round($kcal * $fraction) : null,
                    'whole' => $fraction >= 1.0,
                    'per100g' => $meal->servingGrams === null,
                ];
            }
        }

        return null;
    }

    /** "½ (نصف الصحن)": the plate as a sign and what it means (the owner's request). */
    public static function plate(float $fraction): string
    {
        return match (true) {
            $fraction >= 1.0 => 'الصحن كاملاً',
            $fraction >= 0.75 => '¾ (ثلاثة أرباع الصحن)',
            $fraction >= 0.5 => '½ (نصف الصحن)',
            default => '¼ (ربع الصحن)',
        };
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
