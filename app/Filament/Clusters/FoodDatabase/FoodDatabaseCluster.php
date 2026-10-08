<?php
namespace App\Filament\Clusters\FoodDatabase;

use BackedEnum;
use Filament\Clusters\Cluster;

class FoodDatabaseCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';
    protected static ?string $navigationLabel = 'قاعدة الأغذية';
    protected static ?int $navigationSort     = 1;
}
