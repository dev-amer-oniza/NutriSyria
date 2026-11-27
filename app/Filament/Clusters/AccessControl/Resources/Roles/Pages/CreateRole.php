<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Pages;

use App\Filament\Clusters\AccessControl\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /** A role made here has a code name nobody types or sees; its Arabic name is the label. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['name' => 'custom-'.Str::lower(Str::random(10)), 'guard_name' => 'web'];
    }

    protected function afterCreate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
