<?php
namespace App\Services\Import;

use App\Services\Nutrition\PortionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Import pipeline:  stage() -> validate() -> commit() -> approve()
 *
 *  stage     copies the bundle files into storage and loads every CSV row into import_rows
 *            (nothing touches the food tables)
 *  validate  runs BundleValidator, stores per-row results, marks the batch "validated"
 *  commit    writes committable records as status = pending_review (safe to re-run)
 *  approve   pending_review -> active for a whole batch (stopgap until the review screen exists)
 */
final class BundleImporter
{
    private const SHEETS = [
        'records'         => 'records.csv',
        'nutrient_values' => 'nutrient_values.csv',
        'portions'        => 'portions.csv',
    ];

    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? config('food_import');
    }

    // ------------------------------------------------------------------ stage
    public function stage(string $dir, ?int $userId = null, bool $force = false): int
    {
        $dir = rtrim($dir, '\\/');
        if (! is_dir($dir)) {
            throw new RuntimeException("Folder not found: {$dir}");
        }

        $files = [];
        foreach (self::SHEETS as $sheet => $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                $files[$sheet] = $path;
            }
        }
        if (! isset($files['records'])) {
            throw new RuntimeException("records.csv not found in {$dir}");
        }

        $hash = hash('sha256', implode('', array_map(fn($p) => hash_file('sha256', $p), $files)));
        if (! $force && DB::table('import_batches')->where('file_sha256', $hash)->where('status', 'committed')->exists()) {
            throw new RuntimeException('This exact bundle was already imported. Use --force to import it again.');
        }

        return DB::transaction(function () use ($dir, $files, $hash, $userId) {
            $batchId = DB::table('import_batches')->insertGetId([
                'user_id'           => $userId,
                'original_filename' => basename($dir),
                'file_path'         => 'pending',
                'file_sha256'       => $hash,
                'status'            => 'uploaded',
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            foreach ($files as $sheet => $path) {
                Storage::disk('local')->put("imports/{$batchId}/" . basename($path), file_get_contents($path));
                $this->stageSheet($batchId, $sheet, $path);
            }

            DB::table('import_batches')->where('id', $batchId)->update(['file_path' => "imports/{$batchId}"]);

            return $batchId;
        });
    }

    private function stageSheet(int $batchId, string $sheet, string $path): void
    {
        $handle = fopen($path, 'r') ?: throw new RuntimeException("Cannot open {$path}");
        $header = fgetcsv($handle, 0, ',', '"', '');
        if (! $header) {
            throw new RuntimeException("{$sheet}: file is empty");
        }
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        $header    = array_map('trim', $header);

        $rowNumber = 1;
        $buffer    = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($line === [null]) {
                continue;
            }
            if (count($line) !== count($header)) {
                throw new RuntimeException("{$sheet}: row {$rowNumber} has " . count($line) . ' columns, expected ' . count($header));
            }
            $raw      = array_map(fn($v) => trim((string) $v), array_combine($header, $line));
            $buffer[] = [
                'import_batch_id' => $batchId,
                'sheet'           => $sheet,
                'row_number'      => $rowNumber,
                'raw'             => json_encode($raw, JSON_UNESCAPED_UNICODE),
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
            if (count($buffer) >= 500) {
                DB::table('import_rows')->insert($buffer);
                $buffer = [];
            }
        }
        fclose($handle);
        if ($buffer) {
            DB::table('import_rows')->insert($buffer);
        }
    }

    // --------------------------------------------------------------- validate
    /** @return array report (also stored in import_batches.stats) */
    public function validate(int $batchId): array
    {
        $batch = $this->batch($batchId);
        // دفعة محفوظة: إعادة الفحص تحسب النتائج مقابل القاعدة الحالية وتمسح إحصائيات الحفظ
        if ($batch->status === 'committed') {
            return $this->statsOf($batch)['validation'] ?? [];
        }

        $rows = ['records' => [], 'nutrient_values' => [], 'portions' => []];
        foreach (DB::table('import_rows')->where('import_batch_id', $batchId)->orderBy('id')->cursor() as $r) {
            $rows[$r->sheet][] = ['id' => (int) $r->id, 'row' => (int) $r->row_number, 'raw' => json_decode($r->raw, true)];
        }

        $result = (new BundleValidator(Lookups::load(), $this->cfg))->validate(
            $rows['records'],
            $rows['nutrient_values'],
            $rows['portions'],
            $this->existingRecords($rows['records'])
        );

        $this->persistRowResults($result);

        $report                 = $result['report'];
        $report['issues']       = array_slice($result['issueList'], 0, $this->cfg['max_issues_reported']);
        $report['issues_total'] = count($result['issueList']);

        DB::table('import_batches')->where('id', $batchId)->update([
            'status'     => 'validated',
            'stats'      => json_encode(['validation' => $report], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return $report;
    }

    /** @return array<string,array{id:int,food_id:int}> */
    private function existingRecords(array $recordRows): array
    {
        $refs   = array_values(array_unique(array_filter(array_map(fn($r) => $r['raw']['external_ref'] ?? '', $recordRows))));
        $wanted = [];
        foreach ($recordRows as $r) {
            $wanted[BundleValidator::key($r['raw'])] = true;
        }

        $existing = [];
        foreach (array_chunk($refs, 500) as $chunk) {
            $found = DB::table('food_source_records as r')
                ->join('data_sources as d', 'd.id', '=', 'r.data_source_id')
                ->whereIn('r.external_ref', $chunk)
                ->get(['d.code as source_code', 'r.external_ref', 'r.id', 'r.food_id']);
            foreach ($found as $f) {
                $key = $f->source_code . '|' . $f->external_ref;
                if (isset($wanted[$key])) {
                    $existing[$key] = ['id' => (int) $f->id, 'food_id' => (int) $f->food_id];
                }
            }
        }

        return $existing;
    }

    private function persistRowResults(array $result): void
    {
        $cleanIds = [];
        foreach ($result['rowStatus'] as $rowId => $status) {
            if (isset($result['issues'][$rowId])) {
                DB::table('import_rows')->where('id', $rowId)->update([
                    'status'     => $status,
                    'errors'     => json_encode($result['issues'][$rowId], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            } else {
                $cleanIds[] = $rowId;
            }
        }
        foreach (array_chunk($cleanIds, 1000) as $chunk) {
            DB::table('import_rows')->whereIn('id', $chunk)->update(['status' => 'valid', 'errors' => null, 'updated_at' => now()]);
        }
    }

    // ----------------------------------------------------------------- commit
    /** @return array commit statistics */
    public function commit(int $batchId): array
    {
        $batch = $this->batch($batchId);
        if (! in_array($batch->status, ['validated', 'failed'], true)) {
            throw new RuntimeException("Batch {$batchId} is '{$batch->status}': validate it first (or it was already committed).");
        }

        $l     = Lookups::load();
        $stats = [
            'records_created' => 0, 'records_updated' => 0, 'records_unchanged' => 0, 'records_skipped'  => 0,
            'foods_created'   => 0, 'values_written'  => 0, 'values_unchanged'  => 0, 'portions_written' => 0,
            'portions_updated' => 0, 'portions_kept' => 0, 'notes_updated' => 0,
            'changed_values'  => [],
        ];

        try {
            [$recordRows, $valuesByKey, $portionsByKey] = $this->loadRowsForCommit($batchId);
            // A bundle without portions.csv says nothing about portions: leave the stored ones alone.
            $hasPortionSheet = DB::table('import_rows')->where('import_batch_id', $batchId)->where('sheet', 'portions')->exists();

            foreach ($recordRows as $key => $recordRow) {
                $values  = $valuesByKey[$key] ?? [];
                $blocked = $recordRow->status !== 'valid'
                || count(array_filter($values, fn($v) => $v->status === 'error')) > 0;
                if ($blocked) {
                    $stats['records_skipped']++;
                    continue;
                }

                DB::transaction(function () use ($batchId, $l, $recordRow, $values, $portionsByKey, $key, $hasPortionSheet, &$stats) {
                    $this->commitRecord($batchId, $l, $recordRow, $values, $hasPortionSheet ? ($portionsByKey[$key] ?? []) : null, $stats);
                });
            }

            // Everything still "valid" was not committed: its record was blocked.
            DB::table('import_rows')->where('import_batch_id', $batchId)->where('status', 'valid')
                ->update(['status' => 'skipped', 'updated_at' => now()]);
        } catch (Throwable $e) {
            DB::table('import_batches')->where('id', $batchId)->update([
                'status'     => 'failed',
                'stats'      => json_encode(array_merge($this->statsOf($batch), ['error' => $e->getMessage()]), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            throw $e;
        }

        DB::table('import_batches')->where('id', $batchId)->update([
            'status'       => 'committed',
            'committed_at' => now(),
            'stats'        => json_encode(array_merge($this->statsOf($batch), ['commit' => $stats]), JSON_UNESCAPED_UNICODE),
            'updated_at'   => now(),
        ]);

        return $stats;
    }

    /** @return array{0:array<string,object>,1:array<string,list<object>>,2:array<string,list<object>>} */
    private function loadRowsForCommit(int $batchId): array
    {
        $records  = [];
        $values   = [];
        $portions = [];
        foreach (DB::table('import_rows')->where('import_batch_id', $batchId)
            ->whereIn('status', ['valid', 'error'])->orderBy('id')->cursor() as $r) {
            $r->raw = json_decode($r->raw, true);
            $key    = BundleValidator::key($r->raw);
            match ($r->sheet) {
                'records'         => $records[$key] ??= $r,
                'nutrient_values' => $values[$key][] = $r,
                'portions'        => $portions[$key][]      = $r,
            };
            // A duplicated record row makes the whole key ambiguous: keep it blocked.
            if ($r->sheet === 'records' && $records[$key] !== $r) {
                $records[$key]->status = 'error';
            }
        }

        return [$records, $values, $portions];
    }

    /** @param  list<object>|null  $portionRows  null when the bundle has no portions sheet */
    private function commitRecord(int $batchId, Lookups $l, object $recordRow, array $valueRows, ?array $portionRows, array &$stats): void
    {
        $raw    = $recordRow->raw;
        $dsId   = $l->dataSources[$raw['data_source_code']];
        $formId = $l->forms[$raw['food_form_code']];
        $ref    = $raw['external_ref'];
        $now    = now();

        // ---- food
        $food = $l->resolveFood($raw);
        if ($food['status'] === 'new') {
            $category = trim($raw['category_path'] ?? '');
            $foodId   = DB::table('foods')->insertGetId([
                'name_ar'          => trim($raw['food_name_ar']),
                'name_en'          => ($raw['food_name_en'] ?? '') !== '' ? $raw['food_name_en'] : null,
                'food_category_id' => $category !== '' ? $l->resolveCategoryPath($category) : null,
                'is_active'        => false, // becomes active when a record of it is approved
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
            $l->rememberFood($foodId, $raw['food_name_ar']);
            $stats['foods_created']++;
        } else {
            $foodId = $food['id'];
        }

        // ---- new values, keyed by nutrient id
        $new = [];
        foreach ($valueRows as $vr) {
            if ($vr->status !== 'valid') {
                continue;
            }
            $v               = $vr->raw;
            $nut             = $l->nutrients[$v['nutrient_code']];
            $new[$nut['id']] = [
                'code'   => $v['nutrient_code'],
                'row_id' => $vr->id,
                'data'   => [
                    'nutrient_id'      => $nut['id'],
                    'amount_per_100g'  => ($v['amount_per_100g'] ?? '') === '' ? null : round((float) $v['amount_per_100g'], 6),
                    'unit'             => $nut['unit'],
                    'value_qualifier'  => ($v['value_qualifier'] ?? '') ?: null,
                    'method'           => ($v['method'] ?? '') ?: null,
                    'confidence_level' => ($v['confidence_level'] ?? '') ?: $this->cfg['default_confidence'],
                    'sample_count'     => ($v['sample_count'] ?? '') === '' ? null : (int) $v['sample_count'],
                    'min_amount'       => ($v['min_amount'] ?? '') === '' ? null : round((float) $v['min_amount'], 6),
                    'max_amount'       => ($v['max_amount'] ?? '') === '' ? null : round((float) $v['max_amount'], 6),
                    'notes'            => ($v['notes'] ?? '') ?: null,
                ],
            ];
        }

        // ---- source record (find by the crosswalk key, else create)
        $existing = DB::table('food_source_records')->where('data_source_id', $dsId)->where('external_ref', $ref)->first();
        $old      = $existing
            ? DB::table('food_nutrient_values')->where('food_source_record_id', $existing->id)->get()->keyBy('nutrient_id')
            : collect();

        $toWrite = [];
        $changes = [];
        foreach ($new as $nutrientId => $item) {
            $prev = $old->get($nutrientId);
            if ($prev && $this->sameValue($prev, $item['data'])) {
                $stats['values_unchanged']++;
                continue;
            }
            $toWrite[] = $item['data'];
            if ($prev && count($stats['changed_values']) < $this->cfg['max_changed_values_logged']) {
                $changes[] = ['external_ref' => $ref, 'nutrient' => $item['code'], 'old' => (float) $prev->amount_per_100g, 'new' => $item['data']['amount_per_100g']];
            }
        }

        $formChanged = $existing && (int) $existing->food_form_id !== $formId;
        $changed     = ! $existing || $formChanged || count($toWrite) > 0;

        if (! $existing) {
            $recordId = DB::table('food_source_records')->insertGetId([
                'food_id'         => $foodId,
                'food_form_id'    => $formId,
                'data_source_id'  => $dsId,
                'import_batch_id' => $batchId,
                'external_ref'    => $ref,
                'status'          => 'pending_review',
                'is_preferred'    => false,
                'imported_at'     => $now,
                'notes'           => ($raw['notes'] ?? '') ?: null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $stats['records_created']++;
        } elseif ($changed) {
            $recordId = $existing->id;
            // Changed data must be reviewed again, but never resurrect a rejected/superseded record.
            $status = in_array($existing->status, ['rejected', 'superseded'], true) ? $existing->status : 'pending_review';
            DB::table('food_source_records')->where('id', $recordId)->update([
                'food_form_id'    => $formId,
                'import_batch_id' => $batchId,
                'status'          => $status,
                'imported_at'     => $now,
                'notes'           => ($raw['notes'] ?? '') ?: $existing->notes,
                'updated_at'      => $now,
            ]);
            $stats['records_updated']++;
            array_push($stats['changed_values'], ...$changes);
        } else {
            $recordId = $existing->id;
            $stats['records_unchanged']++;

            // A note is not data: no new review. It is only written when it keeps the current
            // text and adds to it ("مقاربة: … | FDC: …" after "FDC: …"), so a note the admin
            // rewrote is never lost.
            $notes = trim((string) ($raw['notes'] ?? ''));
            $current = trim((string) $existing->notes);
            if ($notes !== '' && $notes !== $current && ($current === '' || str_ends_with($notes, $current))) {
                DB::table('food_source_records')->where('id', $recordId)->update(['notes' => $notes, 'updated_at' => $now]);
                $stats['notes_updated']++;
            }
        }

        // ---- values
        if ($toWrite) {
            $rows  = array_map(fn($d) => $d + ['food_source_record_id' => $recordId, 'created_at' => $now, 'updated_at' => $now], $toWrite);
            DB::table('food_nutrient_values')->upsert(
                $rows,
                ['food_source_record_id', 'nutrient_id'],
                ['amount_per_100g', 'unit', 'value_qualifier', 'method', 'confidence_level', 'sample_count', 'min_amount', 'max_amount', 'notes', 'updated_at']
            );
            $stats['values_written'] += count($toWrite);
        }

        $committedIds = [$recordRow->id];
        foreach ($new as $item) {
            $committedIds[] = $item['row_id'];
        }

        // ---- portions
        if ($portionRows !== null) {
            $this->syncPortions($batchId, $l, $recordId, $foodId, $formId, $portionRows, $stats, $committedIds);
        }

        DB::table('import_rows')->whereIn('id', $committedIds)->update(['status' => 'committed', 'updated_at' => $now]);
    }

    /**
     * Brings the portions this importer owns for one record in line with the bundle WITHOUT
     * deleting and re-inserting them, so the admin's work survives a re-import:
     *  - a portion is matched by unit + amount + source label and updated in place;
     *    is_default, confidence and validity dates are never touched;
     *  - a gram weight the admin corrected (it differs from what this importer last wrote) is kept;
     *  - a portion the source no longer lists is removed, unless it is the default or was corrected.
     * Portions the admin added by hand (no evidence.import_batch_id) are never touched.
     *
     * @param  list<object>  $portionRows
     * @param  list<int>  $committedIds
     */
    private function syncPortions(int $batchId, Lookups $l, int $recordId, int $foodId, int $formId, array $portionRows, array &$stats, array &$committedIds): void
    {
        $now = now();

        $existing = [];   // match key => list of stored rows
        foreach (DB::table('food_portions')->where('food_source_record_id', $recordId)
            ->whereNotNull('evidence->import_batch_id')->orderBy('id')->get() as $row) {
            $row->evidence = json_decode($row->evidence ?? '', true) ?: [];
            $label = $row->evidence['source_label'] ?? $row->evidence['modifier'] ?? $row->description ?? '';
            $existing[self::portionKey((int) $row->measure_unit_id, (float) $row->amount, $label)][] = $row;
        }

        foreach ($portionRows as $pr) {
            if ($pr->status !== 'valid') {
                continue; // portion errors never block a record; the row is simply skipped
            }
            $p      = $pr->raw;
            $label  = trim($p['measure_unit_label']);
            $unitId = $l->resolveUnitLabel($label);
            $amount = ($p['amount'] ?? '') === '' ? 1.0 : (float) $p['amount'];
            $grams  = (float) $p['gram_weight'];

            $fields = [
                'food_id'               => $foodId,
                // keep the source wording ("cup, chopped") when the label carried more than the unit,
                // and always keep a size word ("small" is a piece alias, but it is not just "piece")
                'description'           => ($p['description'] ?? '') ?: ($l->isExactUnitLabel($label) && ! PortionResolver::isSizeWord($label) ? null : $label),
                'food_form_id'          => $formId,
                'basis'                 => ($p['basis'] ?? '') ?: $this->cfg['default_portion_basis'],
                'evidence'              => json_encode([
                    'import_batch_id'      => $batchId,
                    'source_row'           => $pr->row_number,
                    'modifier'             => ($p['modifier'] ?? '') ?: null,
                    'source_label'         => $label,
                    'imported_gram_weight' => $grams,
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'            => $now,
            ];

            $key   = self::portionKey($unitId, $amount, $label);
            $match = isset($existing[$key]) ? array_shift($existing[$key]) : null;

            if ($match) {
                $corrected = self::adminCorrected($match, $grams);
                if (! $corrected) {
                    $fields['gram_weight'] = $grams;
                }
                DB::table('food_portions')->where('id', $match->id)->update($fields);
                $stats['portions_updated']++;
            } else {
                DB::table('food_portions')->insert($fields + [
                    'measure_unit_id'       => $unitId,
                    'amount'                => $amount,
                    'gram_weight'           => $grams,
                    'food_source_record_id' => $recordId,
                    'confidence_level'      => $this->cfg['default_confidence'],
                    'is_default'            => false,
                    'created_at'            => $now,
                ]);
                $stats['portions_written']++;
            }
            $committedIds[] = $pr->id;
        }

        foreach ($existing as $rows) {
            foreach ($rows as $row) {
                if ($row->is_default || self::adminCorrected($row, null)) {
                    $stats['portions_kept']++;

                    continue;
                }
                DB::table('food_portions')->where('id', $row->id)->delete();
            }
        }
    }

    private static function portionKey(?int $unitId, float $amount, string $label): string
    {
        return $unitId.'|'.round($amount, 4).'|'.mb_strtolower(trim($label));
    }

    /**
     * True when the stored gram weight is not what this importer last wrote, i.e. the admin
     * corrected it. Rows imported before imported_gram_weight was recorded count as corrected
     * only when they differ from the incoming source weight (nothing is lost either way).
     */
    private static function adminCorrected(object $row, ?float $incomingGrams): bool
    {
        $lastImported = $row->evidence['imported_gram_weight'] ?? $incomingGrams;

        return $lastImported !== null && abs((float) $row->gram_weight - (float) $lastImported) > 1e-6;
    }

    private function sameValue(object $prev, array $new): bool
    {
        $num = fn($x) => $x === null ? null : round((float) $x, 6);

        return $num($prev->amount_per_100g) === $new['amount_per_100g']
            && ($prev->value_qualifier ?: null) === $new['value_qualifier']
            && ($prev->method ?: null) === $new['method']
            && ($prev->confidence_level ?: null) === $new['confidence_level']
            && $num($prev->min_amount) === $new['min_amount']
            && $num($prev->max_amount) === $new['max_amount']
            && ($prev->sample_count === null ? null : (int) $prev->sample_count) === $new['sample_count'];
    }

    // ---------------------------------------------------------------- approve
    /** Approves EVERY pending_review record of a batch (per-record review comes with the Filament screen). */
    public function approve(int $batchId): array
    {
        $batch = $this->batch($batchId);
        if ($batch->status !== 'committed') {
            throw new RuntimeException("Batch {$batchId} is '{$batch->status}': only committed batches can be approved.");
        }

        return DB::transaction(function () use ($batchId) {
            $pending = fn() => DB::table('food_source_records')
                ->where('import_batch_id', $batchId)->where('status', 'pending_review');

            $foodIds   = $pending()->pluck('food_id')->unique()->values()->all();
            $approved  = $pending()->update(['status' => 'active', 'updated_at' => now()]);
            $activated = $foodIds
                ? DB::table('foods')->whereIn('id', $foodIds)->where('is_active', false)->update(['is_active' => true, 'updated_at' => now()])
                : 0;

            return ['records_approved' => $approved, 'foods_activated' => $activated];
        });
    }

    // ---------------------------------------------------------------- helpers
    private function batch(int $batchId): object
    {
        return DB::table('import_batches')->find($batchId) ?? throw new RuntimeException("Batch {$batchId} not found");
    }

    private function statsOf(object $batch): array
    {
        return json_decode($batch->stats ?? '[]', true) ?: [];
    }
}
