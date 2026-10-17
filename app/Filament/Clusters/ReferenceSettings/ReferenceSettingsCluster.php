<?php
namespace App\Filament\Clusters\ReferenceSettings;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReferenceSettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon  = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::AdjustmentsHorizontal;
    protected static ?string $navigationLabel = 'الإعدادات المرجعية';
    protected static ?string $clusterBreadcrumb = 'الإعدادات المرجعية';
    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';
    protected static ?int $navigationSort     = 1;
}
