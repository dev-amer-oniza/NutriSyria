<?php

namespace App\Services\Import;

use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Approving or rejecting an imported record, with who did it and when. Only records waiting
 * for review change; the caller checks the permission (FoodSourceRecordPolicy::review).
 */
final class RecordReview
{
    public const MAX_REASON_LENGTH = 1000;

    public function approve(FoodSourceRecord $record, ?User $by): bool
    {
        if ($record->status !== FoodSourceStatus::PendingReview) {
            return false;
        }

        DB::transaction(function () use ($record, $by) {
            $this->mark($record, FoodSourceStatus::Active, $by);
            if ($record->food && ! $record->food->is_active) {
                $record->food->update(['is_active' => true]);
            }
        });

        return true;
    }

    /** A rejection always says why: the reason is added to the record's notes. */
    public function reject(FoodSourceRecord $record, ?User $by, string $reason): bool
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('سبب الرفض مطلوب.');
        }
        if ($record->status !== FoodSourceStatus::PendingReview) {
            return false;
        }

        $who = $by?->name ? " ({$by->name})" : '';
        $record->notes = trim(($record->notes ? $record->notes."\n" : '').'رُفض'.$who.': '.mb_substr($reason, 0, self::MAX_REASON_LENGTH));
        $this->mark($record, FoodSourceStatus::Rejected, $by);

        return true;
    }

    private function mark(FoodSourceRecord $record, FoodSourceStatus $status, ?User $by): void
    {
        // reviewed_by / reviewed_at are not fillable: only this class writes them.
        $record->forceFill([
            'status' => $status,
            'reviewed_by' => $by?->getKey(),
            'reviewed_at' => now(),
        ])->save();
    }
}
