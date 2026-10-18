<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Pages;

use App\Filament\Clusters\AccessControl\Resources\Roles\RoleResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterSave(): void
    {
        // The form syncs the pivot directly; Spatie's cache must forget the old permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
