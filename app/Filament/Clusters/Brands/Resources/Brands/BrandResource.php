<?php
namespace App\Filament\Clusters\Brands\Resources\Brands;

use App\Filament\Support\ArabicGlobalSearch;
use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\CreateBrand;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\EditBrand;
use App\Filament\Clusters\Brands\Resources\Brands\Pages\ListBrands;
use App\Filament\Clusters\Brands\Resources\Brands\RelationManagers\CategoriesRelationManager;
use App\Filament\Clusters\Brands\Resources\Brands\RelationManagers\ProductsRelationManager;
use App\Filament\Clusters\Brands\Resources\Brands\Schemas\BrandForm;
use App\Filament\Clusters\Brands\Resources\Brands\Tables\BrandsTable;
use App\Auth\Access;
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

    /**
     * A brand owner manages their own restaurant, not a list of brands (the owner's decisions
     * 1a 3a): the menu says "مطعمي" and opens its page (details, products, categories) directly.
     * An owner of several sees the list of theirs, "مطاعمي".
     */
    public static function ownedBrandIds(): ?array
    {
        $user = auth()->user();

        return $user && $user->allows(Access::BRANDS_MANAGE_OWN) && ! $user->allows(Access::BRANDS_MANAGE_ALL)
            ? (BrandScope::brandIds($user) ?? [])
            : null;
    }

    /** The owner's one restaurant, to open directly; null for admins and owners of several or none. */
    public static function ownBrandId(): ?int
    {
        $ids = static::ownedBrandIds();

        return $ids !== null && count($ids) === 1 ? $ids[0] : null;
    }

    public static function getNavigationLabel(): string
    {
        $ids = static::ownedBrandIds();

        return $ids === null ? 'البراندات' : (count($ids) > 1 ? 'مطاعمي' : 'مطعمي');
    }

    public static function getBreadcrumb(): string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationUrl(): string
    {
        $id = static::ownBrandId();

        return $id ? static::getUrl('edit', ['record' => $id]) : parent::getNavigationUrl();
    }

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
        // For a brand owner "مطعمي" is its details only: products and categories have their own
        // pages beside it (the owner's request). The admin keeps both tabs on each brand.
        if (static::ownedBrandIds() !== null) {
            return [];
        }

        return [
            ProductsRelationManager::class,
            CategoriesRelationManager::class,
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
