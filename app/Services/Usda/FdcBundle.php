<?php

namespace App\Services\Usda;

use Carbon\Carbon;
use Throwable;

/**
 * One USDA food (the API's details) as the three sheets of an import bundle, the same rows
 * tools/usda_to_template.py builds from USDA's files. Pure: the nutrient map comes in.
 */
final class FdcBundle
{
    /**
     * @param  array  $food  FdcClient::food()
     * @param  list<array{code: string, nutrient_code: string, unit: string, factor: float, priority: int}>  $map  our nutrient for each FDC nutrient id
     * @param  array{food_id?: ?int, food_name_ar?: ?string, food_name_en?: ?string, food_form_code: string, note?: ?string}  $choice
     * @return array{records: list<array<string, string>>, nutrient_values: list<array<string, string>>, portions: list<array<string, string>>}
     */
    public static function sheets(array $food, array $map, array $choice): array
    {
        $source = FdcClient::DATA_TYPES[$food['dataType'] ?? ''] ?? null;
        if ($source === null) {
            throw new \InvalidArgumentException('Only Foundation and SR Legacy foods are imported.');
        }
        $ref = (string) $food['fdcId'];

        $notes = 'FDC: '.($food['description'] ?? '').' (published '.self::published($food).')';
        $note = trim((string) ($choice['note'] ?? ''));

        return [
            'records' => [[
                'food_id' => (string) ($choice['food_id'] ?? ''),
                'food_name_ar' => (string) ($choice['food_name_ar'] ?? ''),
                'food_name_en' => (string) ($choice['food_name_en'] ?? ''),
                'category_path' => '',
                'food_form_code' => $choice['food_form_code'],
                'data_source_code' => $source,
                'external_ref' => $ref,
                // The supervisor's note first, the FDC text last (as the matching tool writes it).
                'notes' => ($note === '' ? '' : $note.' | ').$notes,
            ]],
            'nutrient_values' => array_map(fn (array $v) => [
                'data_source_code' => $source,
                'external_ref' => $ref,
                'nutrient_code' => $v['nutrient_code'],
                'amount_per_100g' => self::number($v['amount']),
                'unit' => $v['unit'],
                'method' => 'imported',
                'sample_count' => $v['sample_count'],
                'min_amount' => $v['min'],
                'max_amount' => $v['max'],
                'notes' => $v['derivation'],
            ], self::values($food, $map)),
            'portions' => array_map(fn (array $p) => [
                'data_source_code' => $source,
                'external_ref' => $ref,
                'measure_unit_label' => $p['label'],
                'amount' => self::number($p['amount']),
                'gram_weight' => self::number($p['grams']),
                'description' => $p['description'],
                'modifier' => $p['modifier'],
                'basis' => 'usda',
            ], self::portions($food)),
        ];
    }

    /**
     * Our nutrients in the food: for each, the mapped FDC nutrient with the lowest priority
     * number that the food carries. Negative amounts (USDA's own errors) are left out.
     *
     * @return list<array{nutrient_code: string, unit: string, amount: float, sample_count: string, min: string, max: string, derivation: string}>
     */
    public static function values(array $food, array $map): array
    {
        $byCode = [];
        foreach ($map as $row) {
            $byCode[(string) $row['code']] = $row;
        }

        $best = [];
        foreach ($food['foodNutrients'] ?? [] as $n) {
            $id = (string) ($n['nutrient']['id'] ?? '');
            $amount = $n['amount'] ?? null;
            if (! isset($byCode[$id]) || ! is_numeric($amount) || $amount < 0) {
                continue;
            }
            $row = $byCode[$id];
            $code = $row['nutrient_code'];
            if (isset($best[$code]) && $best[$code]['priority'] <= $row['priority']) {
                continue;
            }
            $derivation = $n['foodNutrientDerivation'] ?? [];
            $best[$code] = [
                'priority' => (int) $row['priority'],
                'nutrient_code' => $code,
                'unit' => $row['unit'],
                'amount' => round((float) $amount * (float) $row['factor'], 6),
                'sample_count' => isset($n['dataPoints']) ? (string) (int) $n['dataPoints'] : '',
                'min' => isset($n['min']) ? self::number($n['min']) : '',
                'max' => isset($n['max']) ? self::number($n['max']) : '',
                'derivation' => trim(($derivation['code'] ?? '').': '.($derivation['description'] ?? ''), ': '),
            ];
        }

        return array_values(array_map(fn (array $v) => array_diff_key($v, ['priority' => 0]), $best));
    }

    /**
     * The food's household portions. The unit's name when USDA gives one, otherwise the
     * modifier ("cup, chopped"), otherwise the description.
     *
     * @return list<array{label: string, amount: float, grams: float, description: string, modifier: string}>
     */
    public static function portions(array $food): array
    {
        $portions = [];
        foreach ($food['foodPortions'] ?? [] as $p) {
            if (! is_numeric($p['gramWeight'] ?? null)) {
                continue;
            }
            $unit = trim((string) ($p['measureUnit']['name'] ?? ''));
            $modifier = trim((string) ($p['modifier'] ?? ''));
            $description = trim((string) ($p['portionDescription'] ?? ''));
            $label = ($unit !== '' && strtolower($unit) !== 'undetermined') ? $unit : ($modifier !== '' ? $modifier : $description);
            if ($label === '') {
                continue;
            }
            $portions[] = [
                'label' => $label,
                'amount' => is_numeric($p['amount'] ?? null) ? (float) $p['amount'] : 1.0,
                'grams' => (float) $p['gramWeight'],
                'description' => $description,
                'modifier' => $modifier,
            ];
        }

        return $portions;
    }

    /** The publication date as USDA's files write it (2019-04-01); the API says 4/1/2019. */
    private static function published(array $food): string
    {
        $date = $food['publicationDate'] ?? $food['publishedDate'] ?? null;
        if (! $date) {
            return '?';
        }
        try {
            return (str_contains($date, '/') ? Carbon::createFromFormat('n/j/Y', $date) : Carbon::parse($date))->toDateString();
        } catch (Throwable) {
            return (string) $date;
        }
    }

    private static function number(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
}
