<?php

namespace App\Filament\Clusters\Kitchen;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * "المطبخ السوري": the platform's own dishes and their sections (the owner's decisions 1 2a 3a
 * 4a). The admin alone works here, and approves each dish's analysis with no review.
 */
class KitchenCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BookOpen;

    protected static ?string $navigationLabel = 'المطبخ السوري';

    protected static ?string $clusterBreadcrumb = 'المطبخ السوري';

    protected static string|UnitEnum|null $navigationGroup = 'المطاعم';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'syrian-kitchen';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }
}
