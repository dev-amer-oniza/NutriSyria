<?php

namespace App\Filament\Clusters\Brands;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BrandsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'البراندات والمنتجات';

    protected static ?string $clusterBreadcrumb = 'البراندات والمنتجات';

    protected static string|UnitEnum|null $navigationGroup = 'المطاعم';

    protected static ?int $navigationSort = 1;
}
