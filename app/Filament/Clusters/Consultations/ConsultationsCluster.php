<?php

namespace App\Filament\Clusters\Consultations;

use App\Filament\Clusters\Consultations\Pages\NutritionistProfilePage;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** A nutritionist's work with clients: public profile, consultation requests, clients. */
class ConsultationsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'الاستشارات';

    protected static ?string $clusterBreadcrumb = 'الاستشارات';

    protected static string|UnitEnum|null $navigationGroup = 'الاستشارات';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return NutritionistProfilePage::canAccess();
    }
}
