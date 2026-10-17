<?php

namespace App\Filament\Clusters\ImportReview;

use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ImportReviewCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;
    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::InboxArrowDown;
    protected static ?string $navigationLabel = 'الاستيراد والمراجعة';
    protected static ?string $clusterBreadcrumb = 'الاستيراد والمراجعة';
    protected static string|UnitEnum|null $navigationGroup = 'البيانات الغذائية';
    protected static ?int $navigationSort = 2;

    /** How many imported records wait for approval: the admin sees it from any page. */
    public static function getNavigationBadge(): ?string
    {
        $pending = FoodSourceRecord::query()->where('status', FoodSourceStatus::PendingReview)->count();

        return $pending ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'سجلات تنتظر الاعتماد';
    }
}
