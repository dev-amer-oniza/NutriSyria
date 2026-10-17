<?php

namespace App\Services\Dashboard;

use App\Enums\FoodSourceStatus;
use App\Enums\ImportBatchStatus;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on the dashboard home page. Foods are counted once each: a food with an approved
 * record is "approved" (usable in recipes), a food with a record waiting for review is "to
 * review"; a food with both counts in both. Records are counted per data source.
 */
final class DatabaseOverview
{
    /**
     * @return array{approved_foods:int, review_foods:int, records:int, approved_records:int,
     *     review_records:int, rejected_records:int}
     */
    public function foods(): array
    {
        $foodsWith = fn (FoodSourceStatus $status) => (int) DB::table('food_source_records')
            ->where('status', $status->value)
            ->distinct()
            ->count('food_id');

        $records = DB::table('food_source_records')
            ->select('status', DB::raw('count(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'approved_foods' => $foodsWith(FoodSourceStatus::Active),
            'review_foods' => $foodsWith(FoodSourceStatus::PendingReview),
            'records' => (int) $records->sum(),
            'approved_records' => (int) ($records[FoodSourceStatus::Active->value] ?? 0),
            'review_records' => (int) ($records[FoodSourceStatus::PendingReview->value] ?? 0),
            'rejected_records' => (int) ($records[FoodSourceStatus::Rejected->value] ?? 0),
        ];
    }

    /**
     * Data sources that hold food records, best priority first.
     *
     * @return list<array{id:int, name:string, approved:int, review:int, rejected:int, last_import:?string}>
     */
    public function sources(): array
    {
        $count = fn (FoodSourceStatus $status, string $as) => DB::raw(
            "sum(case when r.status = '{$status->value}' then 1 else 0 end) as {$as}"
        );

        return DB::table('data_sources as s')
            ->join('food_source_records as r', 'r.data_source_id', '=', 's.id')
            ->groupBy('s.id', 's.name', 's.priority')
            ->orderBy('s.priority')
            ->orderBy('s.name')
            ->get([
                's.id', 's.name',
                $count(FoodSourceStatus::Active, 'approved'),
                $count(FoodSourceStatus::PendingReview, 'review'),
                $count(FoodSourceStatus::Rejected, 'rejected'),
                DB::raw('max(r.imported_at) as last_import'),
            ])
            ->map(fn ($s) => [
                'id' => (int) $s->id, 'name' => $s->name,
                'approved' => (int) $s->approved, 'review' => (int) $s->review, 'rejected' => (int) $s->rejected,
                'last_import' => $s->last_import,
            ])
            ->all();
    }

    /** @return array{committed:int, unfinished:int, failed:int, last_committed_at:?string} */
    public function batches(): array
    {
        $byStatus = DB::table('import_batches')
            ->select('status', DB::raw('count(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'committed' => (int) ($byStatus[ImportBatchStatus::Committed->value] ?? 0),
            // uploaded or checked, but never saved: usually an upload that was abandoned
            'unfinished' => (int) (($byStatus[ImportBatchStatus::Uploaded->value] ?? 0) + ($byStatus[ImportBatchStatus::Validated->value] ?? 0)),
            'failed' => (int) ($byStatus[ImportBatchStatus::Failed->value] ?? 0),
            'last_committed_at' => DB::table('import_batches')->max('committed_at'),
        ];
    }
}
