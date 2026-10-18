<?php
namespace App\Filament\Clusters\Brands\Resources\Products;

use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\ListProducts;
use App\Filament\Clusters\Brands\Resources\Products\Pages\ViewProduct;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\Schemas\ProductForm;
use App\Filament\Clusters\Brands\Resources\Products\Tables\ProductsTable;
use App\Auth\BrandScope;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShoppingBag;

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = BrandsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'المنتجات';

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

    /** Brand owners get their brands' products only; the nutritionist sees all (read-only). */
    public static function getEloquentQuery(): Builder
    {
        return BrandScope::products(parent::getEloquentQuery());
    }

    /** The product's page for the signed-in user: edit when they may change it, else the read-only view. */
    public static function pageUrlFor(Product $product): string
    {
        return static::getUrl(auth()->user()?->can('update', $product) ? 'edit' : 'view', ['record' => $product]);
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecipeIngredientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit'   => EditProduct::route('/{record}/edit'),
            'view'   => ViewProduct::route('/{record}'),
        ];
    }
}
