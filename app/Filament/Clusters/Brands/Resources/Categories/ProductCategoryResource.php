<?php

namespace App\Filament\Clusters\Brands\Resources\Categories;

use App\Auth\BrandScope;
use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Brands\RelationManagers\CategoriesRelationManager;
use App\Filament\Clusters\Brands\Resources\Categories\Pages\ManageProductCategories;
use App\Models\Brand;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * The menu sections, on a page of their own beside "مطعمي" and "المنتجات" (the owner's
 * request): a brand owner sees and orders their restaurant's; the admin sees every brand's.
 */
class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::QueueList;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = BrandsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'التصنيفات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'التصنيفات';

    /** Only the sections of the brands the user manages. */
    public static function getEloquentQuery(): Builder
    {
        $ids = BrandScope::brandIds(auth()->user());

        return $ids === null ? parent::getEloquentQuery() : parent::getEloquentQuery()->whereIn('brand_id', $ids);
    }

    /** The owner of one restaurant never picks it: the field is filled and hidden. */
    public static function onlyBrandId(): ?int
    {
        $ids = BrandScope::brandIds(auth()->user());

        return $ids !== null && count($ids) === 1 ? $ids[0] : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('brand_id')
                ->label('المطعم')
                ->options(fn () => BrandScope::brands(Brand::query())->orderBy('name')->pluck('name', 'id'))
                ->rules(fn () => ($ids = BrandScope::brandIds(auth()->user())) === null ? [] : [Rule::in($ids)])
                ->default(fn () => self::onlyBrandId())
                ->hidden(fn () => self::onlyBrandId() !== null)
                ->disabledOn('edit')
                ->required()
                ->native(false)
                ->live(),
            CategoriesRelationManager::nameField(fn () => null)
                // Unique within the chosen brand (re-bound here, where the brand is a field).
                ->unique('product_categories', 'name', ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get, ?ProductCategory $record) => $rule
                    ->where('brand_id', $record?->brand_id ?? $get('brand_id') ?? self::onlyBrandId())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('brand:id,name')->withCount('products'))
            ->defaultSort(fn (Builder $query) => $query->orderBy('brand_id')->orderBy('sort_order')->orderBy('name'))
            ->reorderable('sort_order')
            ->description('أقسام منيو مطعمك. رتّبها بالسحب كما تريدها على صفحة المطعم في الموقع.')
            ->columns([
                TextColumn::make('name')->label('التصنيف')->weight('bold'),
                TextColumn::make('brand.name')->label('المطعم')->badge()->color('gray')
                    ->hidden(fn () => self::onlyBrandId() !== null),
                TextColumn::make('products_count')->label('المنتجات'),
            ])
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('المطعم')
                    ->options(fn () => BrandScope::brands(Brand::query())->orderBy('name')->pluck('name', 'id'))
                    ->hidden(fn () => self::onlyBrandId() !== null),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('منتجات هذا التصنيف تبقى، وتصير بلا تصنيف.'),
            ])
            ->emptyStateHeading('لا تصنيفات بعد')
            ->emptyStateDescription(fn () => BrandResource::ownedBrandIds() === []
                ? 'تُضاف التصنيفات بعد ربط حسابك بمطعمك.'
                : 'أضف أقسام منيو مطعمك، مثل مقبلات وأطباق رئيسية وحلويات.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductCategories::route('/'),
        ];
    }
}
