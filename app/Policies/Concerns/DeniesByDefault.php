<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Every ability Filament may ask a policy about, all denied. A policy uses this trait and
 * overrides only what a role may actually do, so a forgotten ability is a "no", never a "yes".
 * (The panel runs in strict authorization mode: a model without a policy is an error too.)
 */
trait DeniesByDefault
{
    public function viewAny(User $user): bool { return false; }
    public function view(User $user, mixed $record): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, mixed $record): bool { return false; }
    public function delete(User $user, mixed $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, mixed $record): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, mixed $record): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function replicate(User $user, mixed $record): bool { return false; }
    public function reorder(User $user): bool { return false; }
    public function attach(User $user, mixed $record = null): bool { return false; }
    public function attachAny(User $user): bool { return false; }
    public function detach(User $user, mixed $record = null): bool { return false; }
    public function detachAny(User $user): bool { return false; }
    public function associate(User $user, mixed $record = null): bool { return false; }
    public function associateAny(User $user): bool { return false; }
    public function dissociate(User $user, mixed $record = null): bool { return false; }
    public function dissociateAny(User $user): bool { return false; }
}
