<?php

namespace App\Filament\Clusters\Brands;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class BrandsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'البراندات والمنتجات';

    protected static ?int $navigationSort = 4;
}
