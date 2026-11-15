<?php

namespace App\Services\Import;

use App\Data\Import\ReviewFlag;

/**
 * Pure: why a source record deserves a closer look before it is approved. No database; the
 * caller (BatchReview) passes the record's stored values, its note and what the batch changed.
 *
 * The flags are computed from the values as they are NOW, not stored at import time, so they
 * also cover batches imported before this check existed and records edited by hand since.
 * A record with no flag is "clean" and may be approved with the rest of the clean ones.
 */
final class RecordFlags
{
    private const MACROS = ['protein_g', 'fat_g', 'carbohydrate_g'];

    private const PROXIMATE = ['water_g', 'protein_g', 'fat_g', 'carbohydrate_g', 'ash_g'];

    private const NAMES = [
        'energy_kcal' => 'الطاقة', 'protein_g' => 'البروتين', 'fat_g' => 'الدهون',
        'carbohydrate_g' => 'الكربوهيدرات', 'sugars_g' => 'السكريات', 'fiber_g' => 'الألياف',
    ];

    /**
     * @param  array{energy_mismatch_pct: float|int, large_change_pct: float|int, energy_floor_kcal: float|int,
     *               proximate_sum_tolerance_g: float|int, rounding_tolerance_g: float|int}  $cfg
     */
    public function __construct(private readonly array $cfg) {}

    public static function fromConfig(): self
    {
        $checks = config('food_import.checks');
        $review = config('food_import.review');

        return new self([
            'energy_mismatch_pct' => $review['energy_mismatch_pct'],
            'large_change_pct' => $review['large_change_pct'],
            'energy_floor_kcal' => $checks['atwater_floor_kcal'],
            'proximate_sum_tolerance_g' => $checks['proximate_sum_tolerance_g'],
            'rounding_tolerance_g' => $checks['rounding_tolerance_g'],
        ]);
    }

    /**
     * @param  array<string, array{amount: ?float, min?: ?float, max?: ?float}>  $values  by nutrient code
     * @param  list<array{nutrient: string, old: ?float, new: ?float}>  $changes  what this batch changed in the record
     * @param  array<string, string>  $names  Arabic nutrient names by code, for the messages
     * @return list<ReviewFlag>
     */
    public function for(array $values, ?string $notes = null, array $changes = [], array $names = []): array
    {
        $names += self::NAMES;
        $name = fn (string $code): string => $names[$code] ?? $code;
        $num = fn (string $code): ?float => $values[$code]['amount'] ?? null;
        $flags = [];

        $missing = array_values(array_filter(self::MACROS, fn (string $code) => $num($code) === null));
        if ($missing) {
            $list = implode('، ', array_map($name, $missing));
            $flags[] = new ReviewFlag(ReviewFlag::MISSING_MACRO, 'ناقص: '.$list, "لا توجد قيمة لـ{$list}. تكميل الناقص يحتاج سجلاً معتمداً آخر لنفس الغذاء والشكل.");
        }

        if (($flag = $this->energy($num)) !== null) {
            $flags[] = $flag;
        }

        if (! in_array(null, array_map($num, self::PROXIMATE), true)) {
            $sum = array_sum(array_map($num, self::PROXIMATE));
            if (abs($sum - 100) > $this->cfg['proximate_sum_tolerance_g']) {
                $flags[] = new ReviewFlag(ReviewFlag::PROXIMATE_SUM, 'المجموع ≠ 100 غ', sprintf('الماء + البروتين + الدهون + الكربوهيدرات + الرماد = %s غ لكل 100 غ (المتوقع قرابة 100).', self::fmt($sum)));
            }
        }

        foreach ($this->partsExceedingWhole($num, $name) as $detail) {
            $flags[] = new ReviewFlag(ReviewFlag::PART_EXCEEDS_WHOLE, 'جزء أكبر من الكل', $detail);
        }

        $outside = [];
        foreach ($values as $code => $v) {
            $amount = $v['amount'] ?? null;
            if ($amount === null) {
                continue;
            }
            if ((isset($v['min']) && $amount < $v['min'] - 1e-9) || (isset($v['max']) && $amount > $v['max'] + 1e-9)) {
                $outside[] = sprintf('%s = %s (المجال %s–%s)', $name($code), self::fmt($amount), self::fmt($v['min'] ?? null), self::fmt($v['max'] ?? null));
            }
        }
        if ($outside) {
            $flags[] = new ReviewFlag(ReviewFlag::OUTSIDE_RANGE, 'خارج مجال المصدر', 'قيمة خارج الحد الأدنى والأعلى اللذين ذكرهما المصدر: '.implode('؛ ', $outside).'.');
        }

        $note = trim((string) $notes);
        if (str_starts_with($note, 'مقاربة')) {
            $flags[] = new ReviewFlag(ReviewFlag::PROXY, 'مقاربة', trim(explode(' | ', $note)[0]));
        }

        $big = [];
        foreach ($changes as $c) {
            if ($this->isLargeChange($c['old'] ?? null, $c['new'] ?? null)) {
                $big[] = sprintf('%s: من %s إلى %s', $name($c['nutrient']), self::fmt($c['old']), self::fmt($c['new']));
            }
        }
        if ($big) {
            $flags[] = new ReviewFlag(ReviewFlag::LARGE_CHANGE, 'تغيّر كبير', sprintf('إعادة الاستيراد غيّرت قيماً بأكثر من %s%%: %s.', self::fmt((float) $this->cfg['large_change_pct']), implode('؛ ', $big)));
        }

        return $flags;
    }

    /** Reported energy against 4 × protein + 9 × fat + 4 × carbohydrate. */
    private function energy(callable $num): ?ReviewFlag
    {
        $e = $num('energy_kcal');
        [$p, $f, $c] = [$num('protein_g'), $num('fat_g'), $num('carbohydrate_g')];
        if ($e === null || $p === null || $f === null || $c === null) {
            return null;
        }

        $estimate = 4 * $p + 9 * $f + 4 * $c;
        $gap = abs($e - $estimate);
        if ($gap <= $this->cfg['energy_floor_kcal'] || $gap <= $e * $this->cfg['energy_mismatch_pct'] / 100) {
            return null;
        }

        return new ReviewFlag(ReviewFlag::ENERGY_MISMATCH, 'السعرات لا تطابق', sprintf(
            'السعرات المكتوبة %s، والمحسوبة من البروتين والدهون والكربوهيدرات (4/9/4) %s (فرق %s%%). قد يكون الفرق صحيحاً لغذاء فيه ألياف كثيرة أو كحول سكري.',
            self::fmt($e, 0), self::fmt($estimate, 0), $e > 0 ? self::fmt($gap / $e * 100, 0) : '—',
        ));
    }

    /** @return list<string> */
    private function partsExceedingWhole(callable $num, callable $name): array
    {
        $tol = $this->cfg['rounding_tolerance_g'];
        $out = [];

        $fat = $num('fat_g');
        $fractions = array_filter([$num('fat_sat_g'), $num('fat_mono_g'), $num('fat_poly_g')], fn ($x) => $x !== null);
        if ($fat !== null && $fractions && array_sum($fractions) > $fat + $tol) {
            $out[] = sprintf('الدهون المشبعة والأحادية والمتعددة معاً %s غ، والدهون الكلية %s غ.', self::fmt(array_sum($fractions)), self::fmt($fat));
        }

        $carbs = $num('carbohydrate_g');
        if ($carbs !== null) {
            foreach (['sugars_g', 'fiber_g'] as $code) {
                $part = $num($code);
                if ($part !== null && $part > $carbs + $tol) {
                    $out[] = sprintf('%s %s غ، أكثر من الكربوهيدرات %s غ.', $name($code), self::fmt($part), self::fmt($carbs));
                }
            }
        }

        return $out;
    }

    private function isLargeChange(?float $old, ?float $new): bool
    {
        if ($old === null || $new === null) {
            return $old !== $new;
        }
        if ($old <= 0) {
            return abs($new) > $this->cfg['rounding_tolerance_g'];
        }

        return abs($new - $old) / $old * 100 > $this->cfg['large_change_pct'];
    }

    private static function fmt(?float $n, int $decimals = 1): string
    {
        if ($n === null) {
            return '—';
        }

        $text = number_format($n, $decimals, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
