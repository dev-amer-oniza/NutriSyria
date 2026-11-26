<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Pages\Concerns;

use App\Auth\Access;
use App\Models\User;
use App\Services\Brands\BrandLinks;

/**
 * The role field is not a column: it is taken out of the form data and given to the user with
 * Spatie (one panel role per user). A user who is no longer a brand owner keeps no brands.
 */
trait SavesRole
{
    protected ?string $role = null;

    /** @var list<int> the user's brands before the save, so only new links are told (BrandLinks) */
    protected array $brandsBefore = [];

    protected function takeRole(array $data): array
    {
        $this->brandsBefore = $this->record instanceof User && $this->record->exists ? $this->record->brands()->pluck('brands.id')->all() : [];

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

            return;
        }

        app(BrandLinks::class)->userSaved($user, $this->brandsBefore);
    }
}
