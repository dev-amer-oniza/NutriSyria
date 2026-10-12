<?php

namespace App\Services\Nutrition;

use App\Services\Import\ArabicNormalizer;

/**
 * Pure portion selection: no database, no Eloquent. GramsConverter loads the unit, the
 * ingredient's source record and the food's portions as plain arrays; this class decides which
 * portion (if any) turns the amount into grams. Same split as RecipeCalculator, so the rules
 * stay testable without a database.
 *
 * Rules, in order:
 *  1. Mass units with a base_factor (g, kg, mg, oz, lb) convert directly; no portion needed.
 *  2. Otherwise a portion in the SAME unit, searched from most to least specific:
 *       a. portions of the ingredient's own source record,
 *       b. portions of the same food in the SAME form (from another source),
 *       c. form-less portions of the food.
 *     A portion of another form is never used: a cup of raw chickpeas (200 g) is not a cup of
 *     boiled chickpeas (164 g).
 *  3. Inside that search, a portion marked is_default wins; then a plain portion ("cup",
 *     "1 cup") over a described one ("cup, mashed"); then higher confidence. If portions with
 *     different weights are still tied, the conversion fails as ambiguous instead of picking
 *     one at random: the admin marks a default portion once.
 *  4. Volume units with a base_factor (ml, l, fl oz) may also use a portion in ANOTHER volume
 *     unit of the same food and form: that portion gives the food's density.
 */
final class PortionResolver
{
    /** Two portions whose weights differ by less than this share count as the same weight. */
    private const SAME_WEIGHT_TOLERANCE = 0.01;

    private const CONFIDENCE_RANK = [
        'verified' => 6, 'reviewed' => 5, 'measured' => 4,
        'local_reference' => 3, 'reference' => 2, 'calculated' => 1,
    ];

    /** Portions of these source records are not trusted, unless the ingredient uses that record itself. */
    private const IGNORED_SOURCE_STATUSES = ['rejected', 'superseded'];

    private const MAX_LISTED_OPTIONS = 6;

    /**
     * Size words name a VARIANT of a unit, not the unit itself: "small" and "large" are both
     * aliases of "piece", but a small pepper is 74 g and a medium one 119 g. A portion described
     * only by a size word is therefore never "plain".
     */
    private const SIZE_WORDS = [
        'extra small', 'small', 'medium', 'large', 'extra large', 'jumbo', 'whole',
        'صغير', 'صغيره', 'وسط', 'متوسط', 'متوسطه', 'كبير', 'كبيره', 'كامل', 'كامله',
    ];

    /**
     * @param  array{id:int, code:string, name_ar:string, dimension:?string, base_factor:?float}  $unit
     * @param  array{id:int, food_form_id:?int, food_name_ar:?string, form_name_ar:?string}|null  $record
     *     the ingredient's food_source_record, or null when it does not exist
     * @param  list<array{
     *     id:int, measure_unit_id:int, unit_name_ar:string, unit_dimension:?string, unit_base_factor:?float,
     *     unit_labels:list<string>, amount:float, gram_weight:float, description:?string,
     *     food_source_record_id:?int, food_form_id:?int, form_name_ar:?string, record_status:?string,
     *     is_default:bool, confidence_level:?string,
     * }>  $portions  the food's currently valid portions, in any unit. food_form_id is the
     *     portion's effective form (its own, else its source record's); record_status is the
     *     status of its source record; unit_labels are every name its unit goes by (code, names,
     *     measure_unit_aliases), used to recognise a plain description such as "1 cup"
     */
    public function resolve(array $unit, ?array $record, array $portions, float $amount): GramsConversion
    {
        if (! is_finite($amount) || $amount < 0) {
            return GramsConversion::failed(GramsConversion::INVALID_AMOUNT, 'الكمية غير صالحة.');
        }

        $massFactor = self::massFactor($unit);
        if ($massFactor !== null) {
            return GramsConversion::converted(
                $amount * $massFactor,
                GramsConversion::METHOD_MASS,
                $massFactor === 1.0 ? null : sprintf('1 %s = %s غ', $unit['name_ar'], self::num($massFactor, 6)),
            );
        }

        if ($record === null) {
            return GramsConversion::failed(GramsConversion::UNKNOWN_RECORD, 'سجل المكوّن غير موجود.');
        }

        $usable = array_values(array_filter($portions, fn (array $p) => $p['amount'] > 0 && $p['gram_weight'] > 0
            && ($p['food_source_record_id'] === $record['id']
                || ! in_array($p['record_status'], self::IGNORED_SOURCE_STATUSES, true))));

        $sameUnit = array_values(array_filter($usable, fn (array $p) => $p['measure_unit_id'] === $unit['id']));
        $direct = $this->pick($sameUnit, $record, fn (array $p) => $p['gram_weight'] / $p['amount']);

        if ($direct['status'] === 'ok') {
            $p = $direct['portion'];

            return GramsConversion::converted(
                $amount / $p['amount'] * $p['gram_weight'],
                GramsConversion::METHOD_PORTION,
                'حسب الحصة: '.self::describe($p),
                $p['id'],
            );
        }
        if ($direct['status'] === 'ambiguous') {
            return $this->ambiguous($unit, $record, $direct['options']);
        }

        $otherForms = $direct['other_forms'];
        $isVolume = self::isMeasurableVolume($unit['dimension'] ?? null, $unit['base_factor'] ?? null);

        if ($isVolume) {
            $volumePortions = array_values(array_filter($usable, fn (array $p) => $p['measure_unit_id'] !== $unit['id']
                && self::isMeasurableVolume($p['unit_dimension'], $p['unit_base_factor'])));
            $density = $this->pick($volumePortions, $record, fn (array $p) => self::gramsPerMl($p));

            if ($density['status'] === 'ok') {
                $p = $density['portion'];
                $gramsPerMl = self::gramsPerMl($p);

                return GramsConversion::converted(
                    $amount * (float) $unit['base_factor'] * $gramsPerMl,
                    GramsConversion::METHOD_VOLUME_DENSITY,
                    sprintf('حسب الكثافة من الحصة: %s (%s غ لكل مل)', self::describe($p), self::num($gramsPerMl, 3)),
                    $p['id'],
                );
            }
            if ($density['status'] === 'ambiguous') {
                return $this->ambiguous($unit, $record, $density['options']);
            }
            $otherForms = [...$otherForms, ...$density['other_forms']];
        }

        if ($otherForms) {
            $listed = array_map(
                fn (array $p) => sprintf('%s: %s', $p['form_name_ar'] ?? 'شكل آخر', self::describe($p)),
                array_slice($otherForms, 0, self::MAX_LISTED_OPTIONS),
            );

            return GramsConversion::failed(GramsConversion::OTHER_FORM_ONLY, sprintf(
                'لا توجد حصة «%s» لـ%s. توجد حصص لأشكال أخرى من الغذاء (%s) لكنها لا تُستعمل لأن وزن الوحدة يختلف بين الأشكال. أضف حصة لهذا الشكل من صفحة سجل المصدر.',
                $unit['name_ar'], self::recordName($record), implode('؛ ', $listed),
            ));
        }

        return GramsConversion::failed(GramsConversion::NO_PORTION, sprintf(
            'لا توجد حصة محفوظة تحوّل «%s» إلى غرام لـ%s. أضف حصة من صفحة سجل المصدر%s.',
            $unit['name_ar'], self::recordName($record),
            $isVolume ? '، أو حصة بوحدة حجم أخرى (مل، لتر) ليُحسب الوزن من الكثافة' : '',
        ));
    }

    /** Grams per one unit for mass units, or null when the unit is not a known mass unit. */
    public static function massFactor(array $unit): ?float
    {
        if (($unit['dimension'] ?? null) === 'mass' && (float) ($unit['base_factor'] ?? 0) > 0) {
            return (float) $unit['base_factor'];
        }

        // Units created before dimension/base_factor were filled in.
        return match ($unit['code'] ?? null) {
            'g' => 1.0,
            'kg' => 1000.0,
            default => null,
        };
    }

    /**
     * @param  callable(array): float  $weightOf  grams per one unit (or per ml) of a portion
     * @return array{status: 'ok'|'ambiguous'|'none', portion?: array, options?: list<array>, other_forms: list<array>}
     */
    private function pick(array $candidates, array $record, callable $weightOf): array
    {
        $tiers = [[], [], []];   // own record, same form, form-less
        $otherForms = [];
        foreach ($candidates as $p) {
            $p['_weight'] = $weightOf($p);
            if ($p['food_source_record_id'] === $record['id']) {
                $tiers[0][] = $p;
            } elseif ($p['food_form_id'] === null) {
                $tiers[2][] = $p;
            } elseif ($p['food_form_id'] === $record['food_form_id']) {
                $tiers[1][] = $p;
            } else {
                $otherForms[] = $p;
            }
        }

        // An explicit default is the admin's answer to "which cup?", so it wins over the tiers.
        foreach ($tiers as $tier) {
            $defaults = array_values(array_filter($tier, fn (array $p) => $p['is_default']));
            if ($defaults) {
                return $this->decide($defaults) + ['other_forms' => $otherForms];
            }
        }
        foreach ($tiers as $tier) {
            if ($tier) {
                return $this->decide($tier) + ['other_forms' => $otherForms];
            }
        }

        return ['status' => 'none', 'other_forms' => $otherForms];
    }

    private function decide(array $set): array
    {
        $pool = array_values(array_filter($set, fn (array $p) => self::isPlain($p))) ?: $set;
        if (self::sameWeight($pool)) {
            return ['status' => 'ok', 'portion' => self::best($pool)];
        }

        $top = self::mostConfident($pool);
        if (self::sameWeight($top)) {
            return ['status' => 'ok', 'portion' => self::best($top)];
        }

        return ['status' => 'ambiguous', 'options' => $set];
    }

    private function ambiguous(array $unit, array $record, array $options): GramsConversion
    {
        usort($options, fn (array $a, array $b) => $a['_weight'] <=> $b['_weight']);
        $listed = array_map(fn (array $p) => self::describe($p), array_slice($options, 0, self::MAX_LISTED_OPTIONS));
        $more = count($options) - count($listed);

        return GramsConversion::failed(GramsConversion::AMBIGUOUS_PORTION, sprintf(
            'توجد عدة حصص بأوزان مختلفة تصلح لـ«%s» في %s: %s%s. حدّد إحداها كحصة افتراضية من صفحة سجل المصدر.',
            $unit['name_ar'], self::recordName($record), implode('؛ ', $listed), $more > 0 ? "؛ و{$more} غيرها" : '',
        ));
    }

    /** A portion without its own wording ("cup", "1 cup", "½ كوب"), as opposed to "cup, mashed". */
    private static function isPlain(array $p): bool
    {
        $description = trim((string) ($p['description'] ?? ''));
        if ($description === '') {
            return true;
        }

        $text = ArabicNormalizer::normalize($description);
        $text = trim(preg_replace('/^[\d\s.,\/½¼¾⅓⅔⅛-]+/u', '', $text) ?? $text, " \t,.");
        if ($text === '') {
            return true;
        }

        if (self::isSizeWord($text)) {
            return false;
        }

        $labels = array_map(fn (string $l) => ArabicNormalizer::normalize($l), $p['unit_labels'] ?? []);

        return in_array($text, $labels, true)
            || (str_ends_with($text, 's') && in_array(substr($text, 0, -1), $labels, true)); // "2 cups"
    }

    /** True for "small", "Large", "كبيرة"…: a size of the unit rather than the unit itself. */
    public static function isSizeWord(string $label): bool
    {
        $text = trim(ArabicNormalizer::normalize($label), " \t,.");

        return in_array($text, self::SIZE_WORDS, true);
    }

    private static function sameWeight(array $set): bool
    {
        $weights = array_column($set, '_weight');
        $max = max($weights);

        return $max - min($weights) <= $max * self::SAME_WEIGHT_TOLERANCE;
    }

    private static function mostConfident(array $set): array
    {
        $rank = fn (array $p) => self::CONFIDENCE_RANK[$p['confidence_level'] ?? ''] ?? 0;
        $top = max(array_map($rank, $set));

        return array_values(array_filter($set, fn (array $p) => $rank($p) === $top));
    }

    /** Deterministic choice among portions of (practically) the same weight. */
    private static function best(array $set): array
    {
        usort($set, fn (array $a, array $b) => [
            (int) $b['is_default'],
            self::CONFIDENCE_RANK[$b['confidence_level'] ?? ''] ?? 0,
            (int) self::isPlain($b),
            $a['id'],
        ] <=> [
            (int) $a['is_default'],
            self::CONFIDENCE_RANK[$a['confidence_level'] ?? ''] ?? 0,
            (int) self::isPlain($a),
            $b['id'],
        ]);

        return $set[0];
    }

    private static function isMeasurableVolume(?string $dimension, mixed $baseFactor): bool
    {
        return $dimension === 'volume' && (float) $baseFactor > 0;
    }

    private static function gramsPerMl(array $p): float
    {
        return $p['gram_weight'] / ($p['amount'] * (float) $p['unit_base_factor']);
    }

    private static function describe(array $p): string
    {
        $quantity = sprintf('%s %s = %s غ', self::num($p['amount']), $p['unit_name_ar'], self::num($p['gram_weight']));

        return self::isPlain($p) ? $quantity : sprintf('«%s» (%s)', trim($p['description']), $quantity);
    }

    private static function recordName(array $record): string
    {
        $name = $record['food_name_ar'] ?: "السجل #{$record['id']}";

        return $record['form_name_ar'] ? "{$name} ({$record['form_name_ar']})" : $name;
    }

    private static function num(float $value, int $decimals = 2): string
    {
        return rtrim(rtrim(number_format($value, $decimals, '.', ''), '0'), '.');
    }
}
