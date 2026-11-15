<?php

namespace App\Services\Import;

use App\Data\Import\ReviewFlag;
use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Nutrition\GramsConverter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The review queue of one import batch: what each record is flagged for (RecordFlags), how far
 * the review is, approving every clean record at once, approving a flagged one with a reason,
 * and the foods whose portions still need a default. The caller checks the permission
 * (FoodSourceRecordPolicy::review / reviewAny).
 */
final class BatchReview
{
    /** @var array<int, list<ReviewFlag>> by record id, for the lifetime of this object */
    private array $flags = [];

    public function __construct(
        private readonly RecordReview $review = new RecordReview,
        private readonly ?RecordFlags $rules = null,
        private readonly GramsConverter $grams = new GramsConverter,
    ) {}

    /** @return list<ReviewFlag> */
    public function flagsFor(FoodSourceRecord $record): array
    {
        if (! isset($this->flags[$record->id])) {
            $this->remember(collect([$record]));
        }

        return $this->flags[$record->id];
    }

    /**
     * Approves one record. A flagged record needs a reason, kept in its notes with the name of
     * whoever approved it.
     */
    public function approve(FoodSourceRecord $record, ?User $by, ?string $reason = null): bool
    {
        $flags = $this->flagsFor($record);
        $reason = trim((string) $reason);
        if ($flags && $reason === '') {
            throw new InvalidArgumentException('هذا السجل عليه تنبيه: اكتب سبب اعتماده.');
        }
        if ($record->status !== FoodSourceStatus::PendingReview) {
            return false;
        }

        if ($flags) {
            $who = $by?->name ? " ({$by->name})" : '';
            $labels = implode('، ', array_map(fn (ReviewFlag $f) => $f->label, $flags));
            $record->notes = trim(($record->notes ? $record->notes."\n" : '')
                .'اعتُمد رغم التنبيه'.$who.' ['.$labels.']: '.mb_substr($reason, 0, RecordReview::MAX_REASON_LENGTH));
        }

        return $this->review->approve($record, $by);
    }

    /**
     * Approves the records among $records that wait for review and carry no flag.
     *
     * @param  iterable<mixed>  $records  as a bulk action hands them over
     * @return array{approved:int, flagged:int}
     */
    public function approveClean(iterable $records, ?User $by): array
    {
        $records = collect($records)
            ->filter(fn ($r) => $r instanceof FoodSourceRecord && $r->status === FoodSourceStatus::PendingReview)
            ->values();
        $this->remember($records);

        $approved = 0;
        $flagged = 0;
        foreach ($records as $record) {
            if ($this->flags[$record->id]) {
                $flagged++;
            } elseif ($this->review->approve($record, $by)) {
                $approved++;
            }
        }

        return ['approved' => $approved, 'flagged' => $flagged];
    }

    /** @return array{approved:int, flagged:int} every clean record of the batch, not only one page of it */
    public function approveCleanInBatch(ImportBatch $batch, ?User $by): array
    {
        $total = ['approved' => 0, 'flagged' => 0];
        $batch->foodSourceRecords()
            ->where('status', FoodSourceStatus::PendingReview)
            ->with(['food', 'nutrientValues.nutrient'])
            ->chunkById(200, function (Collection $chunk) use ($by, &$total) {
                $result = $this->approveClean($chunk, $by);
                $total['approved'] += $result['approved'];
                $total['flagged'] += $result['flagged'];
            });

        return $total;
    }

    /**
     * @return array{total:int, active:int, pending:int, flagged:int, rejected:int, other:int}
     *                                                                                         flagged = waiting for review AND flagged
     */
    public function progress(ImportBatch $batch): array
    {
        $counts = $batch->foodSourceRecords()->toBase()
            ->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n) => (int) $n);

        $active = $counts->get(FoodSourceStatus::Active->value, 0);
        $pending = $counts->get(FoodSourceStatus::PendingReview->value, 0);
        $rejected = $counts->get(FoodSourceStatus::Rejected->value, 0);
        $total = $counts->sum();

        return [
            'total' => $total,
            'active' => $active,
            'pending' => $pending,
            'flagged' => count($this->pendingFlaggedIds($batch)),
            'rejected' => $rejected,
            'other' => $total - $active - $pending - $rejected,
        ];
    }

    /** @return list<int> records of the batch waiting for review that carry a flag */
    public function pendingFlaggedIds(ImportBatch $batch): array
    {
        $records = $batch->foodSourceRecords()
            ->where('status', FoodSourceStatus::PendingReview)
            ->with('nutrientValues.nutrient')
            ->get();
        $this->remember($records);

        return $records->filter(fn (FoodSourceRecord $r) => $this->flags[$r->id] !== [])->pluck('id')->values()->all();
    }

    /**
     * Records of the batch whose portions need a default: several weights in one unit and none
     * marked default. Rejected records are left out; nothing uses them.
     *
     * @return array<int, list<array{unit_id:int, unit_name_ar:string, message:string, choices:list<array{id:int, label:string, grams:float}>}>>
     *                                                                                                                                           by record id
     */
    public function ambiguousPortions(ImportBatch $batch): array
    {
        // Only a food with two portions in one unit can be ambiguous: that keeps the check cheap.
        $candidates = $batch->foodSourceRecords()
            ->whereIn('status', [FoodSourceStatus::PendingReview->value, FoodSourceStatus::Active->value])
            ->whereIn('food_id', DB::table('food_portions')
                ->select('food_id')->groupBy('food_id', 'measure_unit_id')->havingRaw('count(*) > 1'))
            ->orderBy('id')
            ->pluck('id');

        $out = [];
        foreach ($candidates as $id) {
            $units = $this->grams->ambiguousUnits((int) $id);
            if ($units) {
                $out[(int) $id] = $units;
            }
        }

        return $out;
    }

    public function makeDefaultPortion(FoodSourceRecord $record, int $unitId, int $portionId): bool
    {
        return $this->grams->makeDefault($record->id, $unitId, $portionId);
    }

    /** @param  Collection<int, FoodSourceRecord>  $records */
    private function remember(Collection $records): void
    {
        $rules = $this->rules ?? RecordFlags::fromConfig();
        $changes = [];

        foreach ($records as $record) {
            if (isset($this->flags[$record->id])) {
                continue;
            }
            $record->loadMissing(['nutrientValues.nutrient', 'importBatch']);

            $batchId = $record->import_batch_id;
            if ($batchId !== null && ! isset($changes[$batchId])) {
                $changes[$batchId] = collect($record->importBatch?->stats['commit']['changed_values'] ?? [])->groupBy('external_ref');
            }

            $values = [];
            $names = [];
            foreach ($record->nutrientValues as $v) {
                $code = $v->nutrient?->code;
                if ($code === null) {
                    continue;
                }
                $values[$code] = [
                    'amount' => $v->amount_per_100g !== null ? (float) $v->amount_per_100g : null,
                    'min' => $v->min_amount !== null ? (float) $v->min_amount : null,
                    'max' => $v->max_amount !== null ? (float) $v->max_amount : null,
                ];
                $names[$code] = $v->nutrient->name_ar;
            }

            $recordChanges = $batchId !== null
                ? ($changes[$batchId]->get($record->external_ref) ?? collect())
                    ->map(fn (array $c) => ['nutrient' => $c['nutrient'], 'old' => isset($c['old']) ? (float) $c['old'] : null, 'new' => isset($c['new']) ? (float) $c['new'] : null])
                    ->values()->all()
                : [];

            $this->flags[$record->id] = $rules->for($values, $record->notes, $recordChanges, $names);
        }
    }
}
