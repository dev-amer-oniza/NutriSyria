<?php

namespace App\Filament\Clusters\AccessControl;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AccessControlCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?string $navigationLabel = 'المستخدمون والصلاحيات';

    protected static ?string $clusterBreadcrumb = 'المستخدمون والصلاحيات';

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 2;
}
