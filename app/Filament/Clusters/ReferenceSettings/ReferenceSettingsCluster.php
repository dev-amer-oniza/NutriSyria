<?php
namespace App\Filament\Clusters\ReferenceSettings;

use BackedEnum;
use Filament\Clusters\Cluster;

class ReferenceSettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'الإعدادات المرجعية';
    protected static ?int $navigationSort     = 3;
}
