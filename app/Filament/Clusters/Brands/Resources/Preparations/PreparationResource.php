<?php

namespace App\Filament\Clusters\Brands\Resources\Preparations;

use App\Auth\BrandScope;
use App\Filament\Clusters\Brands\BrandsCluster;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\CreatePreparation;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\EditPreparation;
use App\Filament\Clusters\Brands\Resources\Preparations\Pages\ListPreparations;
use App\Filament\Clusters\Brands\Resources\Products\Schemas\IngredientRows;
use App\Models\Brand;
use App\Models\Preparation;
use App\Services\Nutrition\RecipePreview;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * "تحضيراتي": a brand's own preparations (كريمة تمر = تمر + مي), written once and picked as an
 * ingredient in any of its products (the owner's decisions 1a 2a 3a 4a). Brands and
 * restaurants only, not the platform kitchen; not the admin (the restaurant side is hidden
 * from them). A preparation in use cannot be deleted.
 */
class PreparationResource extends Resource
{
    protected static ?string $model = Preparation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Beaker;

    protected static ?int $navigationSort = 4;

    protected static ?string $cluster = BrandsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'تحضيراتي';

    protected static ?string $modelLabel = 'تحضير';

    protected static ?string $pluralModelLabel = 'تحضيراتي';

    /** Only the preparations of the brands the user may see, never the platform kitchen's. */
    public static function getEloquentQuery(): Builder
    {
        $ids = BrandScope::brandIds(auth()->user());
        $query = parent::getEloquentQuery()->whereHas('brand', fn (Builder $b) => $b->restaurants());

        return $ids === null ? $query : $query->whereIn('brand_id', $ids);
    }

    public static function onlyBrandId(): ?int
    {
        return ProductCategoryResource::onlyBrandId();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('١. التحضير')
                ->description('شي بتعملو مرة وبتستعملو بأكتر من منتج: كريمة تمر، صوص، عجينة.')
                ->columns(2)
                ->components([
                    Select::make('brand_id')
                        ->label('المطعم أو البراند')
                        ->options(fn () => BrandScope::brands(Brand::query())->restaurants()->orderBy('name')->pluck('name', 'id'))
                        ->rules(fn () => ($ids = BrandScope::brandIds(auth()->user())) === null ? [] : [Rule::in($ids)])
                        ->default(fn () => ProductCategoryResource::onlyBrandId())
                        ->hidden(fn () => ProductCategoryResource::onlyBrandId() !== null)
                        ->disabledOn('edit')
                        ->required()
                        ->native(false)
                        ->live(),
                    TextInput::make('name')
                        ->label('الاسم')
                        ->placeholder('كريمة تمر')
                        ->required()
                        ->maxLength(120)
                        ->unique('preparations', 'name', ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get, ?Preparation $record) => $rule
                            ->where('brand_id', $record?->brand_id ?? $get('brand_id') ?? ProductCategoryResource::onlyBrandId()))
                        ->validationMessages(['unique' => 'عندك تحضير بهذا الاسم.']),
                    TextInput::make('note')
                        ->label('ملاحظة (إلك بس)')
                        ->placeholder('تمر مطحون مع مي سخنة')
                        ->maxLength(300)
                        ->columnSpanFull(),
                ]),
            Section::make('٢. شو فيه؟')
                ->components([IngredientRows::preparationRepeater()]),
            Section::make('٣. بعد التحضير')
                ->components([
                    TextInput::make('weight_after_g')
                        ->label('كم بتوزن بعد التحضير؟ (غ)')
                        ->helperText('إذا فاضي، منعتبرها مجموع المكوّنات. اكتبها إذا الطبخ بينقّص الوزن.')
                        ->numeric()->inputMode('decimal')->minValue(1)
                        ->live(onBlur: true),
                ]),
            Section::make('الـ100 غ منه')
                ->components([
                    View::make('filament.clusters.brands.components.preparation-preview')
                        ->viewData(fn (Get $get) => ['preview' => app(RecipePreview::class)->per100g([
                            'ingredients' => $get('ingredients'), 'weight_after_g' => $get('weight_after_g'),
                        ])]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('brand:id,name')->withCount(['ingredients', 'productLines']))
            ->defaultSort('name')
            ->description('بتختارها بوصفة أي منتج متل أي مكوّن، وبتنكتب بالغرام.')
            ->columns([
                TextColumn::make('name')->label('التحضير')->weight('bold')->searchable(),
                TextColumn::make('brand.name')->label('المطعم')->badge()->color('gray')
                    ->hidden(fn () => ProductCategoryResource::onlyBrandId() !== null),
                TextColumn::make('ingredients_count')->label('المكوّنات'),
                TextColumn::make('product_lines_count')->label('مستعمل بـ')->suffix(' منتج'),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(DeleteAction::make()),
            ])
            ->emptyStateHeading('ما في تحضيرات بعد')
            ->emptyStateDescription(fn () => BrandResource::ownedBrandIds() === []
                ? 'بتنضاف التحضيرات بعد ربط حسابك بمطعمك.'
                : 'أضف شي بتعملو مرة وبتستعملو بأكتر من منتج، متل كريمة التمر.');
    }

    /** A preparation in use is not deleted (the owner's decision 2a): the message names the products. */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Preparation $record): void {
            if ($why = $record->deletionBlocker()) {
                Notification::make()->title('ما فيك تحذفه هلق')->body($why)->danger()->persistent()->send();
                $action->cancel();
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreparations::route('/'),
            'create' => CreatePreparation::route('/create'),
            'edit' => EditPreparation::route('/{record}/edit'),
        ];
    }
}
