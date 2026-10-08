<?php
namespace App\Filament\Clusters\Brands\Resources\Products;

use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Brands\Resources\Products\Pages\ListProducts;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\Schemas\ProductForm;
use App\Filament\Clusters\Brands\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = BrandsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'المنتجات';

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

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
        ];
    }
}
