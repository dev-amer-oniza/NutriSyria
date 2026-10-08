<?php

namespace App\Filament\Clusters\ImportReview;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ImportReviewCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'الاستيراد والمراجعة';
    protected static ?int $navigationSort = 2;
}
