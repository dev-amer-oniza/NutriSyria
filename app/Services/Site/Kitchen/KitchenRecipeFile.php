<?php

namespace App\Services\Site\Kitchen;

/**
 * Reads a kitchen recipe file (database/data/kitchen/<section>.csv): one row an ingredient,
 * the dish's own fields on its first row. Pure: text in, dishes and the reasons a dish was
 * set aside out. Columns (header row, any order):
 *   dish, category, description, servings, weight_after_g, with_bread (1 or empty),
 *   ingredient (the food's Arabic name), form (food_forms.code), grams, after (1 = added after cooking)
 * weight_after_g is the plate's weight as served, what is added after cooking included.
 */
final class KitchenRecipeFile
{
    public const COLUMNS = ['dish', 'category', 'description', 'servings', 'weight_after_g', 'with_bread', 'ingredient', 'form', 'grams', 'after'];

    /**
     * @return array{dishes: list<array{name: string, category: string, description: ?string, servings: int, weight_after_g: float, with_bread: bool, lines: list<array{food: string, form: string, grams: float, after: bool}>}>, problems: array<string, list<string>>}
     */
    public static function parse(string $csv): array
    {
        $rows = array_map(fn (string $line) => str_getcsv($line), preg_split('/\R/u', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))));
        $header = array_map(fn ($h) => trim((string) $h), array_shift($rows) ?? []);
        if ($missing = array_diff(self::COLUMNS, $header)) {
            return ['dishes' => [], 'problems' => ['الملف' => ['أعمدة ناقصة: '.implode('، ', $missing)]]];
        }

        $dishes = [];
        $problems = [];
        $current = null;
        foreach ($rows as $n => $cells) {
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = array_map(fn ($c) => trim((string) $c), array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), '')));
            $name = $row['dish'] ?: ($current ?? '');
            if ($name === '') {
                $problems['الملف'][] = 'السطر '.($n + 2).' بلا اسم أكلة.';

                continue;
            }
            if (! isset($dishes[$name])) {
                $dishes[$name] = [
                    'name' => $name,
                    'category' => $row['category'],
                    'description' => $row['description'] !== '' ? $row['description'] : null,
                    'servings' => (int) $row['servings'],
                    'weight_after_g' => (float) $row['weight_after_g'],
                    'with_bread' => $row['with_bread'] === '1',
                    'lines' => [],
                ];
                if ($row['category'] === '' || (int) $row['servings'] < 1 || (float) $row['weight_after_g'] <= 0) {
                    $problems[$name][] = 'سطرها الأول يحتاج التصنيف وعدد الحصص والوزن بعد الطبخ.';
                }
            }
            $current = $name;

            if ($row['ingredient'] === '' || $row['form'] === '' || (float) $row['grams'] <= 0) {
                $problems[$name][] = 'السطر '.($n + 2).' يحتاج المكوّن وشكله وغراماته.';

                continue;
            }
            $dishes[$name]['lines'][] = ['food' => $row['ingredient'], 'form' => $row['form'], 'grams' => (float) $row['grams'], 'after' => $row['after'] === '1'];
        }

        return ['dishes' => array_values($dishes), 'problems' => $problems];
    }
}
