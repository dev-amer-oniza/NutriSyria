<?php
namespace App\Filament\Clusters\FoodDatabase;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FoodDatabaseCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cake;
    protected static ?string $navigationLabel = 'قاعدة الأغذية';
    protected static ?string $clusterBreadcrumb = 'قاعدة الأغذية';
    protected static string|UnitEnum|null $navigationGroup = 'البيانات الغذائية';
    protected static ?int $navigationSort     = 1;
}
