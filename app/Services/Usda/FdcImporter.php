<?php

namespace App\Services\Usda;

use App\Exceptions\ImportFileRejected;
use App\Models\FoodSourceRecord;
use App\Models\Nutrient;
use App\Models\NutrientSourceCode;
use App\Services\Import\BundleImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Brings one USDA food into the database the way every import comes in (the owner's
 * decision 3a): a one-food batch, staged, checked by the same validator and saved as
 * "pending review". A food already in the database is reported, never added twice.
 */
class FdcImporter
{
    public function __construct(private readonly FdcClient $client) {}

    /**
     * Which of these USDA foods the database already holds.
     *
     * @param  list<int>  $fdcIds
     * @return array<int, FoodSourceRecord> by fdc id, with food, form and source loaded
     */
    public function existing(array $fdcIds): array
    {
        if ($fdcIds === []) {
            return [];
        }

        return FoodSourceRecord::query()
            ->with(['food:id,name_ar', 'foodForm:id,name_ar', 'dataSource:id,code,name'])
            ->whereIn('external_ref', array_map('strval', $fdcIds))
            ->whereHas('dataSource', fn ($q) => $q->whereIn('code', array_values(FdcClient::DATA_TYPES)))
            ->get()
            ->keyBy(fn (FoodSourceRecord $r) => (int) $r->external_ref)
            ->all();
    }

    /** Our nutrients for FDC's nutrient ids (nutrient_source_codes), for FdcBundle. */
    public function nutrientMap(): array
    {
        return NutrientSourceCode::query()->where('source_system', 'usda_fdc')->with('nutrient:id,code,unit,name_ar,display_order')->get()
            ->filter(fn (NutrientSourceCode $c) => $c->nutrient !== null)
            ->map(fn (NutrientSourceCode $c) => [
                'code' => (string) $c->code,
                'nutrient_code' => $c->nutrient->code,
                'unit' => $c->nutrient->unit,
                'factor' => (float) $c->factor,
                'priority' => (int) $c->priority,
            ])->values()->all();
    }

    /**
     * What an import would bring: our nutrients (Arabic names, in display order) and portions.
     *
     * @return array{food: array, values: list<array{name: string, amount: float, unit: string}>, portions: list<array>}
     */
    public function preview(int $fdcId): array
    {
        $food = $this->client->food($fdcId);
        $nutrients = Nutrient::query()->get(['code', 'name_ar', 'display_order'])->keyBy('code');

        $values = collect(FdcBundle::values($food, $this->nutrientMap()))
            ->sortBy(fn (array $v) => $nutrients[$v['nutrient_code']]->display_order ?? 9999)
            ->map(fn (array $v) => ['name' => $nutrients[$v['nutrient_code']]->name_ar ?? $v['nutrient_code'], 'amount' => $v['amount'], 'unit' => $v['unit']])
            ->values()->all();

        return ['food' => $food, 'values' => $values, 'portions' => FdcBundle::portions($food)];
    }

    /**
     * Adds the food as a pending-review record through a one-food batch.
     *
     * @param  array{food_id?: ?int, food_name_ar?: ?string, food_name_en?: ?string, food_form_code: string, note?: ?string}  $choice
     * @return array{batch: int, stats: array}
     *
     * @throws ImportFileRejected when it is already in the database, or the checks block it
     */
    public function add(int $fdcId, array $choice, ?int $userId = null): array
    {
        if ($record = $this->existing([$fdcId])[$fdcId] ?? null) {
            throw new ImportFileRejected(self::existsMessage($record));
        }

        $food = $this->client->food($fdcId);
        $sheets = FdcBundle::sheets($food, $this->nutrientMap(), $choice);
        if ($sheets['nutrient_values'] === []) {
            throw new ImportFileRejected('هذا الغذاء في USDA بلا أي قيمة غذائية نعرفها، فلم يُضف.');
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

            // force: the same USDA food may be added again after its record was deleted.
            $batch = $importer->stage($dir, $userId, force: true);
            DB::table('import_batches')->where('id', $batch)->update(['original_filename' => 'USDA: '.Str::limit($food['description'] ?? (string) $fdcId, 200)]);

            $report = $importer->validate($batch);
            if (($report['records']['ready'] ?? 0) < 1) {
                $reasons = collect($report['issues'] ?? [])->where('level', 'error')->pluck('message')->unique()->take(3)->implode(' · ');
                throw new ImportFileRejected('الفحص العلمي منع إضافة هذا الغذاء'.($reasons !== '' ? ': '.$reasons : '.'));
            }

            return ['batch' => $batch, 'stats' => $importer->commit($batch)];
        } catch (ImportFileRejected $e) {
            if ($batch) {
                $importer->discard($batch);   // a batch that saved nothing would hang in the list
            }
            throw $e;
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public static function existsMessage(FoodSourceRecord $record): string
    {
        return 'موجود في القاعدة: '.($record->food?->name_ar ?? '—')
            .($record->foodForm ? ' ('.$record->foodForm->name_ar.')' : '')
            .'، وحالته: '.$record->status->getLabel().'.';
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
