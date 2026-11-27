<?php

namespace App\Filament\Clusters\AccessControl\Resources\Permissions\Pages;

use App\Filament\Clusters\AccessControl\Resources\Permissions\PermissionResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePermissions extends ManageRecords
{
    protected static string $resource = PermissionResource::class;
}
