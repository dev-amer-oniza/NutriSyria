<?php

namespace App\Filament\Clusters\Kitchen\Resources\FoodRequests;

use App\Filament\Clusters\Kitchen\KitchenCluster;
use App\Filament\Clusters\Kitchen\Resources\FoodRequests\Pages\ListFoodRequests;
use App\Filament\Clusters\Kitchen\Resources\FoodRequests\Tables\FoodRequestsTable;
use App\Models\FoodRequest;
use App\Services\Daily\FoodRequests;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "أكلات مطلوبة": dishes people ate and could not find on the site, with what is in them
 * (FoodRequests). The admin adds the dish (to the kitchen or a restaurant), then links the
 * request to it; everyone who asked for it hears in the site bell.
 */
class FoodRequestResource extends Resource
{
    protected static ?string $model = FoodRequest::class;

    protected static ?string $cluster = KitchenCluster::class;

    protected static ?string $slug = 'requested-foods';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::InboxArrowDown;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'أكلات مطلوبة';

    protected static ?string $modelLabel = 'أكلة مطلوبة';

    protected static ?string $pluralModelLabel = 'أكلات مطلوبة';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'product']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = app(FoodRequests::class)->pendingCount();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'أكلات تنتظر الإضافة';
    }

    public static function table(Table $table): Table
    {
        return FoodRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFoodRequests::route('/'),
        ];
    }
}
