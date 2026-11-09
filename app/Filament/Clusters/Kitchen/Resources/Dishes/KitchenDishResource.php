<?php

namespace App\Filament\Clusters\Kitchen\Resources\Dishes;

use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Filament\Clusters\Brands\Resources\Products\RelationManagers\RecipeIngredientsRelationManager;
use App\Filament\Clusters\Brands\Resources\Products\Schemas\ProductForm;
use App\Filament\Clusters\Kitchen\Concerns\KitchenAccess;
use App\Filament\Clusters\Kitchen\KitchenCluster;
use App\Filament\Clusters\Kitchen\Pages\KitchenRecipeAnalysis;
use App\Filament\Clusters\Kitchen\Resources\Dishes\Pages\CreateKitchenDish;
use App\Filament\Clusters\Kitchen\Resources\Dishes\Pages\EditKitchenDish;
use App\Filament\Clusters\Kitchen\Resources\Dishes\Pages\ListKitchenDishes;
use App\Filament\Support\Placeholders;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ArabicSearch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * The kitchen's dishes: the same recipe and analysis as a restaurant's product, on the
 * platform's own brand, approved by the admin with no review (the owner's decisions).
 */
class KitchenDishResource extends Resource
{
    use KitchenAccess;

    protected static ?string $model = Product::class;

    protected static ?string $cluster = KitchenCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cake;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'dishes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'الوجبات';

    protected static ?string $modelLabel = 'وجبة';

    protected static ?string $pluralModelLabel = 'الوجبات';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('brand', fn (Builder $b) => $b->where('is_platform', true));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('بيانات الوجبة')
                ->columns(2)
                ->components([
                    TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                    Select::make('category_id')
                        ->label('التصنيف')
                        ->placeholder('بلا تصنيف')
                        ->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query
                            ->where('brand_id', Brand::platform()->id)->menuOrder())
                        ->rules(fn () => [Rule::exists('product_categories', 'id')->where('brand_id', Brand::platform()->id)])
                        ->native(false)
                        ->createOptionForm([ProductCategoryResource::nameField(fn () => Brand::platform()->id)])
                        ->createOptionUsing(function (array $data): int {
                            $category = new ProductCategory(['name' => $data['name']]);
                            $category->brand_id = Brand::platform()->id;
                            $category->sort_order = (int) ProductCategory::where('brand_id', $category->brand_id)->max('sort_order') + 1;
                            $category->save();

                            return $category->getKey();
                        }),
                    Toggle::make('is_active')
                        ->label('ظاهرة على الموقع')
                        ->default(true)
                        ->helperText('تظهر بعد اعتماد تحليلها فقط.'),
                    Textarea::make('description')->label('الوصف')->columnSpanFull(),
                ]),
            ...ProductForm::recipeSections(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => NutritionApprovalStatus::withApprovalData($query)->with('category'))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('img')->disk('public')->label('')->circular()->defaultImageUrl(Placeholders::image('product')),
                TextColumn::make('name')->label('الوجبة')->searchable(query: ArabicSearch::column('name'))->sortable()->weight('bold'),
                TextColumn::make('category.name')->label('التصنيف')->placeholder('بلا تصنيف'),
                TextColumn::make('nutrition_status')
                    ->label('القيم الغذائية')
                    ->state(fn ($record) => NutritionApprovalStatus::of($record))
                    ->badge(),
                IconColumn::make('is_active')->label('ظاهرة')->boolean(),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query
                        ->where('brand_id', Brand::platform()->id)->menuOrder()),
            ])
            ->recordActions([
                Action::make('analyze')
                    ->label('تحليل')
                    ->icon('heroicon-o-calculator')
                    ->url(fn (Product $record) => KitchenRecipeAnalysis::getUrl().'?product='.$record->id),
                EditAction::make(),
            ])
            ->emptyStateHeading('لا وجبات بعد')
            ->emptyStateDescription('أضف أول وجبة من المطبخ السوري، ثم مكوّناتها، ثم حلّلها واعتمدها.');
    }

    public static function getRelations(): array
    {
        return [RecipeIngredientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKitchenDishes::route('/'),
            'create' => CreateKitchenDish::route('/create'),
            'edit' => EditKitchenDish::route('/{record}/edit'),
        ];
    }
}
