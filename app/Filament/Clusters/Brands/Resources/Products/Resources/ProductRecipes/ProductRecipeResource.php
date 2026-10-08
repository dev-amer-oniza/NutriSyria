<?php
namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes;

use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Pages\CreateProductRecipe;
use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Pages\EditProductRecipe;
use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\RelationManagers\IngredientsRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Schemas\ProductRecipeForm;
use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Tables\ProductRecipesTable;
use App\Models\ProductRecipe;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductRecipeResource extends Resource
{
    protected static ?string $model = ProductRecipe::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ProductResource::class;
    protected static ?string $modelLabel     = 'وصفة';

    protected static ?string $pluralModelLabel = 'الوصفة';

    public static function form(Schema $schema): Schema
    {
        return ProductRecipeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductRecipesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            IngredientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateProductRecipe::route('/create'),
            'edit'   => EditProductRecipe::route('/{record}/edit'),
        ];
    }
}
