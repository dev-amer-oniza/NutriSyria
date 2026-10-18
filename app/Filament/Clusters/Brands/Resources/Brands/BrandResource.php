<?php
namespace App\Filament\Clusters\Brands\Resources\Brands;

use App\Filament\Support\ArabicGlobalSearch;
use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\CreateBrand;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\ListBrands;
use App\Filament\Clusters\Brands\Resources\Brands\RelationManagers\ProductsRelationManager;
use App\Filament\Clusters\Brands\Resources\Brands\Schemas\BrandForm;
use App\Filament\Clusters\Brands\Resources\Brands\Tables\BrandsTable;
use App\Auth\BrandScope;
use App\Models\Brand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BrandResource extends Resource
{
    use ArabicGlobalSearch;

    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Tag;

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = BrandsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'البراندات';

    protected static ?string $modelLabel = 'براند';

    protected static ?string $pluralModelLabel = 'البراندات';

    /** Brand owners get their own brands only: in the list, on record pages and in search. */
    public static function getEloquentQuery(): Builder
    {
        return BrandScope::brands(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return BrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BrandsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBrands::route('/'),
            'create' => CreateBrand::route('/create'),
            'edit'   => EditBrand::route('/{record}/edit'),
        ];
    }
}
