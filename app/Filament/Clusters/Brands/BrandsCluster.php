<?php

namespace App\Filament\Clusters\Brands;

use App\Auth\Access;
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

    /**
     * Named after what each role finds in it: a brand owner their restaurant and its products,
     * the admin the brands only (products are not theirs), the nutritionist the products to review.
     */
    public static function getNavigationLabel(): string
    {
        $user = auth()->user();

        return match (true) {
            self::forOwner() => 'مطعمي ومنتجاته',
            (bool) $user?->isSuperAdmin() => 'البراندات',
            $user !== null && ! $user->allows(Access::BRANDS_MANAGE_ALL) && ! $user->allows(Access::BRANDS_MANAGE_OWN) => 'منتجات المطاعم',
            default => static::$navigationLabel,
        };
    }

    public static function getClusterBreadcrumb(): ?string
    {
        return static::getNavigationLabel();
    }

    private static function forOwner(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->allows(Access::BRANDS_MANAGE_OWN) && ! $user->allows(Access::BRANDS_MANAGE_ALL);
    }
}
