<?php

namespace App\Services\Import;

use App\Data\Import\ReviewFlag;
use App\Exceptions\ImportFileRejected;
use App\Models\DataSource;
use App\Models\Food;
use App\Models\FoodSourceRecord;
use App\Models\Nutrient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A food that is not in USDA (حبة البركة, a ready-made product), with values the admin types
 * from a label, a published table or a website (the owner's decisions 1a 2a). It goes through
 * the same one-food batch and checks as a USDA food, under one source, "إدخال يدوي", ranked
 * after USDA: for a food and form USDA also has, USDA's numbers come first and these fill gaps.
 * The admin typed and saw the values, so the record is approved at once; a check warning
 * needs a reason, written into the record's note as on the review queue.
 */
class ManualFoodImporter
{
    public const SOURCE = 'manual_entry';

    /** After USDA Foundation (90), SR Legacy (100) and FNDDS (120): the owner's decision 1a. */
    public const PRIORITY = 130;

    /** Asked for every food: what every analysis and every athlete's target needs. */
    public const REQUIRED = ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g'];

    /** As on a nutrition label: used by the meal checks when known, "لا تتوفر قيمة" when not. */
    public const LABEL = ['fat_sat_g', 'sugars_g', 'fiber_g', 'sodium_mg'];

    /** The one source every typed-in food is saved under, created the first time it is needed. */
    public static function source(): DataSource
    {
        return DataSource::query()->firstOrCreate(['code' => self::SOURCE], [
            'name' => 'إدخال يدوي (ملصق، جدول منشور، موقع)',
            'type' => 'manual',
            'priority' => self::PRIORITY,
            'is_active' => true,
        ]);
    }

    /**
     * The review warnings these values would raise, before anything is saved.
     *
     * @param  array<string, mixed>  $values  by nutrient code, per 100 g
     * @return list<ReviewFlag>
     */
    public function flags(array $values): array
    {
        $names = Nutrient::query()->pluck('name_ar', 'code')->all();

        return RecordFlags::fromConfig()->for(self::amounts($values), names: $names);
    }

    /**
     * @param  array{food_id?: ?int, food_name_ar?: ?string, food_name_en?: ?string, food_form_code: string}  $choice
     * @param  array<string, mixed>  $values  by nutrient code, per 100 g; empty ones are left out
     * @param  list<array{measure_unit_id: int|string, amount: mixed, gram_weight: mixed}>  $portions
     * @param  ?list<string>  $allergens  for a new food: its allergens (null = not checked)
     * @return array{batch: int, record: FoodSourceRecord}
     *
     * @throws ImportFileRejected when a value is missing, a warning has no reason, or the checks block it
     */
    public function add(array $choice, array $values, array $portions, string $source, ?string $reason, ?User $by, ?array $allergens = null): array
    {
        $amounts = self::amounts($values);
        $missing = array_diff(self::REQUIRED, array_keys($amounts));
        if ($missing) {
            throw new ImportFileRejected('اكتب السعرات والبروتين والدهون والكربوهيدرات لكل 100 غ.');
        }
        $source = trim($source);
        if ($source === '') {
            throw new ImportFileRejected('اكتب من أين جاءت الأرقام.');
        }
        $flags = $this->flags($values);
        if ($flags && trim((string) $reason) === '') {
            throw new ImportFileRejected('على هذه القيم تنبيه: اكتب سبب حفظها كما هي.');
        }

        $dataSource = self::source();
        $ref = 'manual-'.Str::lower((string) Str::ulid());
        $units = DB::table('measure_unit_aliases')->orderBy('id')->get(['measure_unit_id', 'label'])
            ->unique('measure_unit_id')->pluck('label', 'measure_unit_id');

        $sheets = [
            'records' => [[
                'food_id' => (string) ($choice['food_id'] ?? ''),
                'food_name_ar' => trim((string) ($choice['food_name_ar'] ?? '')),
                'food_name_en' => trim((string) ($choice['food_name_en'] ?? '')),
                'category_path' => '',
                'food_form_code' => $choice['food_form_code'],
                'data_source_code' => self::SOURCE,
                'external_ref' => $ref,
                'notes' => 'المصدر: '.Str::limit($source, 300),
            ]],
            'nutrient_values' => [],
            'portions' => [],
        ];
        $nutrientUnits = Nutrient::query()->pluck('unit', 'code');
        foreach ($amounts as $code => $v) {
            $sheets['nutrient_values'][] = [
                'data_source_code' => self::SOURCE, 'external_ref' => $ref, 'nutrient_code' => $code,
                'amount_per_100g' => self::number($v['amount']), 'unit' => (string) ($nutrientUnits[$code] ?? ''),
                'method' => 'imported',
            ];
        }
        foreach ($portions as $p) {
            $label = $units[(int) ($p['measure_unit_id'] ?? 0)] ?? null;
            if ($label === null || ! is_numeric($p['amount'] ?? null) || ! is_numeric($p['gram_weight'] ?? null)) {
                continue;
            }
            $sheets['portions'][] = [
                'data_source_code' => self::SOURCE, 'external_ref' => $ref, 'measure_unit_label' => $label,
                'amount' => self::number((float) $p['amount']), 'gram_weight' => self::number((float) $p['gram_weight']),
                'basis' => 'reference',
            ];
        }

        $dir = storage_path('app/import-staging/'.Str::uuid());
        File::ensureDirectoryExists($dir);
        $importer = new BundleImporter;
        $batch = null;

        try {
            foreach ($sheets as $sheet => $rows) {
                if ($rows !== []) {
                    self::writeCsv("$dir/$sheet.csv", $rows);
                }
            }

            $batch = $importer->stage($dir, $by?->id);
            $name = $choice['food_name_ar'] ?? null ?: Food::find($choice['food_id'] ?? 0)?->name_ar;
            DB::table('import_batches')->where('id', $batch)->update(['original_filename' => 'إدخال يدوي: '.Str::limit((string) $name, 200)]);

            $report = $importer->validate($batch);
            if (($report['records']['ready'] ?? 0) < 1) {
                $reasons = collect($report['issues'] ?? [])->where('level', 'error')->pluck('message')->unique()->take(3)->implode(' · ');
                throw new ImportFileRejected('الفحص العلمي منع الحفظ'.($reasons !== '' ? ': '.$reasons : '.'));
            }

            $importer->commit($batch);
            $record = FoodSourceRecord::query()->where('data_source_id', $dataSource->id)->where('external_ref', $ref)->firstOrFail();
            (new BatchReview)->approve($record, $by, $reason);

            if ($allergens !== null && empty($choice['food_id'])) {
                $record->food->forceFill(['allergens' => array_values($allergens), 'allergens_checked' => true])->save();
            }

            return ['batch' => $batch, 'record' => $record->refresh()];
        } catch (ImportFileRejected $e) {
            if ($batch) {
                $importer->discard($batch);
            }
            throw $e;
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * The filled values, as RecordFlags reads them.
     *
     * @return array<string, array{amount: float}>
     */
    private static function amounts(array $values): array
    {
        $out = [];
        foreach ($values as $code => $value) {
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $out[(string) $code] = ['amount' => (float) $value];
            }
        }

        return $out;
    }

    private static function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 6, '.', ''), '0'), '.') ?: '0';
    }

    /** @param  non-empty-list<array<string, string>>  $rows */
    private static function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows[0]), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);
    }
}
