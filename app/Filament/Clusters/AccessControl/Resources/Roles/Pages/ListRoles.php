<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Pages;

use App\Filament\Clusters\AccessControl\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;
}
