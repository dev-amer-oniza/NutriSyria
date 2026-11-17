<?php

namespace App\Services\Nutrition\Fit;

use App\Data\Nutrition\CriteriaSheet;
use App\Data\Nutrition\CriterionValue;
use App\Data\Nutrition\MealFacts;
use App\Data\Nutrition\MealFit;
use App\Enums\Allergen;
use App\Enums\CriterionSource;
use App\Services\Nutrition\References\MedicalReferences;

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
 *  - the suitable portion is the largest part of the serving within every counted limit.
 */
class MealFitCalculator
{
    private const MAX_REASONS = 3;

    /** Below this share of the serving, a smaller portion is not offered. */
    public const MIN_PORTION_SHARE = 0.4;

    /** @param  array{fit_tolerance: array{suits: int, moderate: int, unfit: int}, fit_target_limits: list<string>, fit_near_pct?: int, legume_fibre?: array{min_fiber_g: float, foods: list<string>}, criteria?: array<string, array<string, mixed>>}  $rules  config('nutrition_criteria') */
    public function __construct(private readonly array $rules) {}

    public function judge(CriteriaSheet $sheet, MealFacts $meal): MealFit
    {
        $overs = [];   // by nutrient: [criterion, amount, limit, percent over, level]
        $unders = [];  // notes: below a minimum, by nutrient
        $info = [];    // rules not verified yet that the meal goes past, by nutrient
        $missing = [];
        $factor = 1.0; // the share of the serving within every counted limit

        foreach ($sheet->criteria as $criterion) {
            $amount = $meal->amount($criterion->nutrient);

            // A rule not verified yet never judges (step 2): shown when the meal goes past it. An
            // information-only criterion (total sugars) brings no such line either.
            $infoOnly = $this->rules['criteria'][$criterion->key]['info_only'] ?? false;
            foreach ($infoOnly ? [] : array_filter([$criterion->verified ? null : $criterion, $criterion->pending]) as $rule) {
                $limit = $sheet->perMeal($rule);
                if ($amount !== null && $rule->bound === 'max' && $limit > 0 && $amount > $limit) {
                    $info[$rule->nutrient] ??= [$rule, $amount, $limit];
                }
            }
            if (! $criterion->verified) {
                continue;
            }

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

        $reasons = [
            ...array_map(fn (string $a) => $this->allergenReason($sheet, $a), $found),
            ...($sheet->allergens && ! $meal->allergensChecked && ! $found
                ? ['لم نتحقق بعد من مسبّبات الحساسية في كل مكوّناتها، فتأكد منها مع المطعم.'] : []),
            ...array_map(fn (array $o) => $this->overReason($sheet, ...array_slice($o, 0, 4)), $overs),
        ];
        if (! $overs && ! $found) {
            $reasons[] = $this->energyReason($sheet, $meal);
        }

        $notes = [
            ...$this->legumeNote($sheet, $meal),
            ...array_map(fn (array $u) => $this->underNote($sheet, ...$u), array_values($unders)),
            ...($missing ? ['لا تتوفر قيمة '.implode(' و', array_unique($missing)).' لهذه الوجبة، فلم تدخل في التقييم.'] : []),
        ];

        return new MealFit(
            level: $level,
            reasons: array_slice(array_values(array_filter($reasons)), 0, self::MAX_REASONS),
            share: $this->share($sheet, $meal),
            portion: $found ? null : $this->portion($meal, $factor),
            notes: $notes,
            // A template not verified yet is shown, but the verified shares judged (decision 1a).
            template: ($sheet->template['verified'] ?? true)
                ? $sheet->template['label']
                : $sheet->template['judging'].' (قالب '.$sheet->template['label'].' قيد التوثيق)',
            basis: $sheet->meal ? sprintf('غدائك في خطة أخصائيّك (%s%s سعرة)', $sheet->meal['time'] ? $sheet->meal['time'].'، ' : '', number_format($sheet->meal['kcal'])) : null,
            info: $this->infoLines(array_values($info)),
            grounds: $this->grounds($sheet),
            general: $sheet->pendingConditions !== [],
            sources: (new MedicalReferences($this->rules, []))->anchorFor($sheet->conditions, $sheet->template['key']),
        );
    }

    /**
     * "للمعلومة، لا يدخل في التقييم بعد: …" for each rule not verified yet the meal goes past,
     * the first one telling how to make it count: the doctor's number, typed in معاييري.
     *
     * @param  list<array{0: CriterionValue, 1: float, 2: float}>  $info
     * @return list<string>
     */
    private function infoLines(array $info): array
    {
        return array_map(function (array $one, int $i): string {
            [$rule, $amount, $limit] = $one;
            $line = sprintf('للمعلومة، لا يدخل في التقييم بعد: %s فيها %s %s، وحد «%s» %s %s للوجبة قيد التوثيق.',
                $rule->label, self::number($amount), $rule->unit, $rule->origin ?? 'حالتك', self::number($limit), $rule->unit);

            return $i === 0 ? $line.' إن حدّد لك طبيبك هذا الحد، اكتبه في معاييري فنقيّم عليه.' : $line;
        }, $info, array_keys($info));
    }

    /** "قيّمناها على: …" what judged the meal, and what is still waiting for its references. */
    private function grounds(CriteriaSheet $sheet): string
    {
        $sources = array_map(fn (CriterionValue $c) => $c->source, array_filter($sheet->criteria, fn (CriterionValue $c) => $c->verified));
        $parts = [
            'احتياجك العام (موثّق)',
            ...($sheet->avoided ? ['وتجنّب '.implode(' و', array_map(fn (string $a) => Allergen::tryFrom($a)?->label() ?? $a, array_keys($sheet->avoided)))] : []),
            ...(in_array(CriterionSource::Doctor, $sources, true) ? ['وأرقام طبيبك'] : []),
            ...($sheet->meal ? ['وغداؤك في خطة أخصائيّك'] : (in_array(CriterionSource::Nutritionist, $sources, true) ? ['وأرقام أخصائيّك'] : [])),
            ...(in_array(CriterionSource::User, $sources, true) ? ['وما حددته أنت'] : []),
        ];
        $line = 'قيّمناها على: '.implode('، ', $parts).'.';

        return $sheet->pendingConditions
            ? $line.' قيد التوثيق: حدود '.implode(' و', $sheet->pendingConditions).'. ننصحك بأخصائي.'
            : $line;
    }

    private function allergenReason(CriteriaSheet $sheet, string $allergen): string
    {
        $name = Allergen::tryFrom($allergen)?->label() ?? $allergen;

        return isset($sheet->avoided[$allergen])
            ? 'فيها '.$name.'، '.$sheet->avoided[$allergen].'.'
            : 'فيها '.$name.'، وكتبتَ في ملفك أنه يسبّب لك حساسية.';
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

    /** The level an overshoot gives; only a strict limit may say "not for you now" (step 2). */
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
            ? sprintf('سعراتها %s، أي %d%% من احتياجك اليومي، %s، وكل الحدود الموثّقة محترمة.', self::number($kcal), (int) round($kcal / $day * 100), $within)
            : 'كل الحدود الموثّقة محترمة في هذه الوجبة.';
    }

    /** @return list<string> the legume fibre note, for a sensitive digestion only (2a) */
    private function legumeNote(CriteriaSheet $sheet, MealFacts $meal): array
    {
        $rule = $this->rules['legume_fibre'] ?? null;
        $fiber = $meal->amount('fiber_g');
        if (! $rule || ! $sheet->sensitiveDigestion || $fiber === null || $fiber < $rule['min_fiber_g']
            || array_intersect($meal->ingredients, $rule['foods']) === []) {
            return [];
        }

        return [sprintf('فيها %s غ ألياف من البقوليات، وقد تسبّب غازات أو انتفاخاً لبعض من حالتهم مثل حالتك. إن لم تعتدها فابدأ بكمية صغيرة، وتقشير الحب يسهّل هضمه.', self::number($fiber))];
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
