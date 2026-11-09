<?php

namespace App\Filament\Clusters\Kitchen\Resources\Categories;

use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Filament\Clusters\Kitchen\Concerns\KitchenAccess;
use App\Filament\Clusters\Kitchen\KitchenCluster;
use App\Filament\Clusters\Kitchen\Resources\Categories\Pages\ManageKitchenCategories;
use App\Models\Brand;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** The kitchen's sections ("مقبلات", "طبخات"...), in the order shown on the site. */
class KitchenCategoryResource extends Resource
{
    use KitchenAccess;

    protected static ?string $model = ProductCategory::class;

    protected static ?string $cluster = KitchenCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::QueueList;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'categories';

    protected static ?string $navigationLabel = 'التصنيفات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'التصنيفات';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('brand', fn (Builder $b) => $b->where('is_platform', true));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            ProductCategoryResource::nameField(fn () => Brand::platform()->id),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('products'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->description('أقسام المطبخ السوري. رتّبها بالسحب كما تريدها على الموقع.')
            ->columns([
                TextColumn::make('name')->label('التصنيف')->weight('bold'),
                TextColumn::make('products_count')->label('الوجبات'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('وجبات هذا التصنيف تبقى، وتصير بلا تصنيف.'),
            ])
            ->emptyStateHeading('لا تصنيفات بعد')
            ->emptyStateDescription('أضف أقسام المطبخ السوري، مثل مقبلات وطبخات وحلويات.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageKitchenCategories::route('/')];
    }
}
