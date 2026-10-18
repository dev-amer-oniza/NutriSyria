<?php

namespace App\Policies;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use App\Models\User;
use App\Policies\Concerns\FoodDataPolicy;

/**
 * Source records are food data (foods.view / foods.manage), and also what an import batch
 * holds: whoever may see batches may read them, and imports.review may approve or reject
 * a record that waits for review.
 */
class FoodSourceRecordPolicy
{
    use FoodDataPolicy;

    protected function canRead(User $user): bool
    {
        return $user->allows(Access::FOODS_VIEW)
            || $user->allows(Access::FOODS_MANAGE)
            || $user->allows(Access::IMPORTS_VIEW);
    }

    public function review(User $user, FoodSourceRecord $record): bool
    {
        return $record->status === FoodSourceStatus::PendingReview && $this->reviewAny($user);
    }

    public function reviewAny(User $user): bool
    {
        return $user->allows(Access::IMPORTS_REVIEW);
    }
}
