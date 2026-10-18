<?php

namespace App\Policies;

use App\Auth\Access;
use App\Models\ImportBatch;
use App\Models\User;
use App\Policies\Concerns\DeniesByDefault;

/**
 * Batches: the nutritionist opens a batch to review its records (the batch page itself has
 * nothing editable); uploading, matching and deleting batches stays with imports.upload.
 */
class ImportBatchPolicy
{
    use DeniesByDefault;

    public function viewAny(User $user): bool
    {
        return $user->allows(Access::IMPORTS_VIEW) || $user->allows(Access::IMPORTS_UPLOAD);
    }

    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    /** The batch page is where its records are reviewed. */
    public function update(User $user, ImportBatch $batch): bool
    {
        return $user->allows(Access::IMPORTS_REVIEW) || $user->allows(Access::IMPORTS_UPLOAD);
    }

    public function create(User $user): bool
    {
        return $user->allows(Access::IMPORTS_UPLOAD);
    }

    public function delete(User $user, ImportBatch $batch): bool
    {
        return $user->allows(Access::IMPORTS_UPLOAD);
    }

    public function deleteAny(User $user): bool
    {
        return $user->allows(Access::IMPORTS_UPLOAD);
    }
}
