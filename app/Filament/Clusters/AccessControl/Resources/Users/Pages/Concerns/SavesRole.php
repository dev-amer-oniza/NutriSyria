<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Pages\Concerns;

use App\Auth\Access;
use App\Models\User;

/**
 * The role field is not a column: it is taken out of the form data and given to the user with
 * Spatie (one panel role per user). A user who is no longer a brand owner keeps no brands.
 */
trait SavesRole
{
    protected ?string $role = null;

    protected function takeRole(array $data): array
    {
        $this->role = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function applyRole(User $user): void
    {
        if (! array_key_exists($this->role, Access::roles())) {
            return;
        }

        $user->syncRoles([$this->role]);
        if ($this->role !== Access::BRAND_OWNER) {
            $user->brands()->detach();
        }
    }
}
