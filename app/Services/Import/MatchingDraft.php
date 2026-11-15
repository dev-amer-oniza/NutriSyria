<?php

namespace App\Services\Import;

/**
 * Pure: the draft the matching tool (usda_to_template.py suggest) writes, read so the picks can
 * be made in the dashboard instead of in Excel. No database, no files: the page passes the CSV
 * text in and gets the reviewed CSV back for `build`.
 *
 * The rules are the tool's own:
 *  - one pick per food, form AND release: the best Foundation item and the best SR Legacy item
 *    may both be picked (recipes use the higher priority and borrow what it lacks);
 *  - one food per USDA item: the same fdc_id is never picked twice.
 */
final class MatchingDraft
{
    private const SOURCES = [
        'usda_fdc_foundation' => 'Foundation',
        'usda_fdc_sr_legacy' => 'SR Legacy',
        'usda_fdc_fndds' => 'FNDDS',
    ];

    private const NUTRIENTS = [
        'energy' => 'الطاقة', 'protein' => 'البروتين', 'fat' => 'الدهون', 'carbohydrate' => 'الكربوهيدرات',
    ];

    /**
     * @param  list<string>  $header
     * @param  list<array<string, string>>  $rows
     */
    private function __construct(private readonly array $header, private readonly array $rows) {}

    public static function fromCsv(string $csv): self
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = [];
        $rows = [];
        while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            if ($header === []) {
                $header = array_map(fn ($h) => trim((string) $h), $line);

                continue;
            }
            $line = array_pad(array_slice($line, 0, count($header)), count($header), '');
            $rows[] = array_map(fn ($v) => trim((string) $v), array_combine($header, $line));
        }
        fclose($handle);

        return new self($header, $rows);
    }

    /** @return list<array<string, string>> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /** @return array<int, bool> the tool's own pre-marked picks */
    public function initialPicks(): array
    {
        $picks = [];
        foreach ($this->rows as $i => $row) {
            if (self::isYes($row['pick'] ?? '')) {
                $picks[$i] = true;
            }
        }

        return $picks;
    }

    /**
     * Food and form pairs, in the draft's order, each with its candidate rows.
     *
     * @return list<array{key:string, food_name_ar:string, food_name_en:string, target_form:string, note:string, rows:list<int>, empty:bool}>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->rows as $i => $row) {
            $key = self::groupKey($row);
            $groups[$key] ??= [
                'key' => $key,
                'food_name_ar' => $row['food_name_ar'] ?? '',
                'food_name_en' => $row['food_name_en'] ?? '',
                'target_form' => $row['target_form'] ?? '',
                'note' => $row['note'] ?? '',
                'rows' => [],
                'empty' => true,
            ];
            $groups[$key]['rows'][] = $i;
            if (($row['fdc_id'] ?? '') !== '') {
                $groups[$key]['empty'] = false;
            }
        }

        return array_values($groups);
    }

    /**
     * Picks $i, or drops it if it was picked. Picking a row drops the other row of the same food,
     * form and release, so one click moves the choice.
     *
     * @param  array<int, bool>  $picks
     * @return array<int, bool>
     */
    public function toggle(array $picks, int $i): array
    {
        $row = $this->rows[$i] ?? null;
        if ($row === null || ($row['fdc_id'] ?? '') === '') {
            return $picks;
        }
        if (! empty($picks[$i])) {
            unset($picks[$i]);

            return $picks;
        }

        foreach (array_keys(array_filter($picks)) as $j) {
            $other = $this->rows[$j] ?? null;
            if ($other && self::groupKey($other) === self::groupKey($row) && ($other['data_source'] ?? '') === ($row['data_source'] ?? '')) {
                unset($picks[$j]);
            }
        }
        $picks[$i] = true;

        return $picks;
    }

    /**
     * What stops a build, in Arabic: the same USDA item picked for two foods, two picks of one
     * release for one food and form, or nothing picked at all.
     *
     * @param  array<int, bool>  $picks
     * @return list<string>
     */
    public function problems(array $picks): array
    {
        $chosen = $this->chosen($picks);
        if ($chosen === []) {
            return ['لم تختر أي غذاء بعد.'];
        }

        $problems = [];
        $byFdc = [];
        $bySlot = [];
        foreach ($chosen as $i => $row) {
            $byFdc[$row['fdc_id']][] = $i;
            $bySlot[self::groupKey($row).'|'.($row['data_source'] ?? '')][] = $i;
        }
        foreach ($byFdc as $fdcId => $rows) {
            if (count($rows) > 1) {
                $problems[] = sprintf('عنصر USDA %s مختار لأكثر من غذاء: %s. كل عنصر لغذاء واحد.', $fdcId, implode('، ', array_map(fn ($i) => $this->label($this->rows[$i]), $rows)));
            }
        }
        foreach ($bySlot as $rows) {
            if (count($rows) > 1) {
                $row = $this->rows[$rows[0]];
                $problems[] = sprintf('%s: اختيار واحد فقط من %s.', $this->label($row), self::sourceLabel($row['data_source'] ?? ''));
            }
        }

        return $problems;
    }

    /**
     * @param  array<int, bool>  $picks
     * @return array{pairs:int, chosen:int, undecided:int, no_candidate:int}
     */
    public function summary(array $picks): array
    {
        $summary = ['pairs' => 0, 'chosen' => 0, 'undecided' => 0, 'no_candidate' => 0];
        foreach ($this->groups() as $group) {
            $summary['pairs']++;
            if ($group['empty']) {
                $summary['no_candidate']++;
            } elseif (array_filter($group['rows'], fn ($i) => ! empty($picks[$i]))) {
                $summary['chosen']++;
            } else {
                $summary['undecided']++;
            }
        }

        return $summary;
    }

    /**
     * The reviewed draft, ready for `build`: every row kept, pick = yes on the chosen ones.
     *
     * @param  array<int, bool>  $picks
     */
    public function toCsv(array $picks): string
    {
        $header = in_array('pick', $this->header, true) ? $this->header : ['pick', ...$this->header];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, ',', '"', '');
        foreach ($this->rows as $i => $row) {
            $row['pick'] = ! empty($picks[$i]) ? 'yes' : '';
            fputcsv($handle, array_map(fn ($h) => $row[$h] ?? '', $header), ',', '"', '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /** The tool's English hint, in Arabic. */
    public static function hintAr(string $hint): string
    {
        if (preg_match('/^same USDA item already picked for (.+?): /u', $hint, $m)) {
            return 'العنصر نفسه مختار من قبل لـ'.$m[1];
        }

        return match (true) {
            $hint === '' => '',
            str_starts_with($hint, 'weak name match') => 'الاسم لا يطابق تماماً: تأكد',
            str_starts_with($hint, 'several similar candidates') => 'عدة مرشحين متقاربين: اختر واحداً',
            str_starts_with($hint, 'incomplete analysis: no ') => 'تحليل ناقص: لا '.self::nutrientsAr(substr($hint, strlen('incomplete analysis: no '))),
            default => $hint,
        };
    }

    /** How the name matched, in Arabic. */
    public static function matchAr(string $match): string
    {
        return match ($match) {
            'exact' => 'الاسم مطابق',
            'alias' => 'اسم بديل',
            'starts-with' => 'يبدأ بالاسم',
            'loose' => 'تطابق جزئي',
            default => $match,
        };
    }

    public static function formNoteAr(string $note): string
    {
        return str_starts_with($note, 'form not stated by USDA') ? 'الشكل غير مذكور في USDA، افتُرض من قائمتك' : $note;
    }

    /** "protein, fat" → "البروتين، الدهون" */
    public static function nutrientsAr(string $list): string
    {
        $names = array_filter(array_map('trim', explode(',', $list)));

        return implode('، ', array_map(fn ($n) => self::NUTRIENTS[$n] ?? $n, $names));
    }

    public static function sourceLabel(string $code): string
    {
        return self::SOURCES[$code] ?? $code;
    }

    /** @param  array<int, bool>  $picks
     * @return array<int, array<string, string>> */
    private function chosen(array $picks): array
    {
        $out = [];
        foreach (array_keys(array_filter($picks)) as $i) {
            if (isset($this->rows[$i]) && ($this->rows[$i]['fdc_id'] ?? '') !== '') {
                $out[$i] = $this->rows[$i];
            }
        }

        return $out;
    }

    private function label(array $row): string
    {
        return trim(($row['food_name_ar'] ?? '').' ('.($row['target_form'] ?? '').')');
    }

    private static function groupKey(array $row): string
    {
        return ($row['food_name_ar'] ?? '').'|'.($row['target_form'] ?? '');
    }

    private static function isYes(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['yes', 'y', 'x', '1', 'true'], true);
    }
}
