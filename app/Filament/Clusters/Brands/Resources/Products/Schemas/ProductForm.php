<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Schemas;

use App\Auth\BrandScope;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Support\ImageUpload;
use App\Livewire\ReviewNotesPanel;
use App\Models\Preparation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Nutrition\RecipePreview;
use App\Support\Amount;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Where the dish stands: a path to read, not a status to pick (the owner's request).
                View::make('filament.clusters.brands.components.product-steps')
                    ->viewData(fn ($record) => ['status' => self::statusOf($record)]),
                // First on the page, so a brand owner opening it from a notification sees it at once.
                Section::make('ملاحظة المراجع')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn ($record) => $record
                        && ($record->reviewNotes()->exists() || auth()->user()?->can('sendReviewNote', $record)))
                    ->components([
                        Livewire::make(ReviewNotesPanel::class, fn ($record) => ['productId' => $record->id]),
                    ]),
                Section::make('١. الطبق')
                    ->description('اسمه وصورته متل ما بيشوفهن الزبون.')
                    ->columns(2)
                    ->components(self::dishFields()),
                ...self::recipeSteps(),
                // The result, filling in while the owner types: what one plate holds, and what is missing.
                Section::make('الحصة الواحدة')
                    ->icon('heroicon-o-calculator')
                    ->components([
                        View::make('filament.clusters.brands.components.recipe-preview')
                            ->viewData(fn (Get $get) => ['preview' => app(RecipePreview::class)->of($get('recipe'))]),
                    ]),
                self::officialAnalysis(),
            ]);
    }

    /** @return list<Component|Field> */
    private static function dishFields(): array
    {
        return [
            Select::make('brand_id')
                ->label('المطعم أو البراند')
                // Offered: the brands the user may see (the nutritionist reads every product's
                // brand). Accepted on save: only the brands the user manages (rules below).
                ->relationship('brand', 'name', modifyQueryUsing: fn ($query) => BrandScope::brands($query, readOnly: true))
                ->arabicSearch(fn ($query) => BrandScope::brands($query, readOnly: true))
                ->rules(fn () => ($ids = BrandScope::brandIds(auth()->user())) === null ? [] : [Rule::in($ids)])
                // A brand owner with a single brand does not have to pick it, nor see the field.
                ->default(fn () => count($ids = BrandScope::brandIds(auth()->user()) ?? []) === 1 ? $ids[0] : null)
                ->extraFieldWrapperAttributes(fn () => BrandResource::ownBrandId() ? ['style' => 'display: none'] : [])
                ->required()
                ->searchable()
                ->preload()
                ->native(false)
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('category_id', null))
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('اسم الطبق')
                ->placeholder('مثلاً: مجدرة بالبرغل')
                ->required()
                ->maxLength(255),
            // The menu section: one of the chosen brand's own (the owner's decisions 1a 2a 3a).
            Select::make('category_id')
                ->label('القسم بالمنيو')
                ->placeholder('بلا قسم')
                ->relationship('category', 'name', modifyQueryUsing: fn ($query, Get $get) => $query
                    ->where('brand_id', $get('brand_id') ?? 0)->menuOrder())
                ->rules(fn (Get $get) => [Rule::exists('product_categories', 'id')->where('brand_id', $get('brand_id') ?? 0)])
                ->disabled(fn (Get $get) => blank($get('brand_id')))
                ->native(false)
                ->createOptionForm(fn (Get $get) => [ProductCategoryResource::nameField(fn () => $get('brand_id'))])
                ->createOptionUsing(function (array $data, Get $get): int {
                    $brandId = (int) $get('brand_id');
                    $allowed = BrandScope::brandIds(auth()->user());
                    abort_unless($brandId && ($allowed === null || in_array($brandId, $allowed, true)), 403);

                    $category = new ProductCategory(['name' => $data['name']]);
                    $category->brand_id = $brandId;
                    $category->sort_order = (int) ProductCategory::where('brand_id', $brandId)->max('sort_order') + 1;
                    $category->save();

                    return $category->getKey();
                }),
            self::imageField(),
            Textarea::make('description')
                ->label('وصف قصير')
                ->placeholder('شو بيميّزه؟ سطر أو سطرين للزبون.')
                ->rows(3),
            Toggle::make('eaten_with_bread')
                ->label('بيتاكل مع خبز')
                ->default(false),
            Toggle::make('is_active')
                ->label('ظاهر بالموقع')
                ->default(true),
        ];
    }

    /** The status strip's input: the product read with its approval data (null while creating). */
    private static function statusOf(mixed $record): ?NutritionApprovalStatus
    {
        if (! $record instanceof Product) {
            return null;
        }
        $product = NutritionApprovalStatus::withApprovalData(Product::query())->find($record->getKey());

        return $product instanceof Product ? NutritionApprovalStatus::of($product) : null;
    }

    /**
     * "٢. شو فيه؟" and "٣. الطبخة": the recipe on the product page itself (the owner's request),
     * saved with the product. Shared with the platform kitchen's dishes (KitchenDishResource).
     *
     * @return list<Group>
     */
    public static function recipeSteps(): array
    {
        return [
            Group::make([
                Section::make('٢. شو فيه؟')
                    ->description('كل مكوّن بكميته متل ما بتطبخه. الوزن بيطلع لحاله.')
                    // A preparation the dish needs (كريمة تمر) is made once on its own page and
                    // then picked here like a food ("· تحضيري"). Restaurants and brands only.
                    ->headerActions([
                        Action::make('newPreparation')
                            ->label('تحضير جديد')
                            ->icon(Heroicon::OutlinedPlus)
                            ->link()
                            // Same tab, with the way back to this product (the owner's request);
                            // unsaved changes here are guarded by the panel's leave-page alert.
                            ->url(fn ($livewire) => PreparationResource::getUrl('create', ['return' => ($product = $livewire->getRecord()) instanceof Product
                                ? ProductResource::getUrl('edit', ['record' => $product]) : ProductResource::getUrl('create')]))
                            ->visible(fn () => (bool) auth()->user()?->can('create', Preparation::class)),
                    ])
                    ->components([IngredientRows::repeater()]),
                Section::make('٣. الطبخة')
                    ->columns(2)
                    ->components([
                        TextInput::make('servings')
                            ->label('كم حصة بتطلع الطبخة؟')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('any')
                            ->default(1)
                            ->minValue(0.5)
                            ->maxValue(9999)
                            ->live(onBlur: true)
                            ->required()
                            ->helperText(fn (Get $get) => self::servingHint($get('servings'), $get('weight_after_cooking_g'))),
                        TextInput::make('weight_after_cooking_g')
                            ->label('كم بتوزن الطبخة كلها بعد الطبخ؟')
                            ->numeric()
                            ->inputMode('decimal')
                            ->minValue(0)
                            ->suffix('غرام')
                            ->live(onBlur: true)
                            ->helperText('زِن القدر فاضي وبعدين مليان، والفرق هو وزن الطبخة.'),
                    ]),
            ])->relationship('recipe'),
        ];
    }

    /**
     * The kitchen dish form's recipe part: the same steps, its picture and the official analysis.
     *
     * @return list<Group|Section>
     */
    public static function recipeSections(): array
    {
        return [
            ...self::recipeSteps(),
            Section::make('الصورة')->components([self::imageField()]),
            self::officialAnalysis(),
        ];
    }

    /** "الحصة = اللي بتبيعه…", and the portion's weight once both numbers are written. */
    public static function servingHint(mixed $servings, mixed $weight): string
    {
        $hint = 'الحصة = اللي بتبيعه للزبون: صحن، كيس، علبة. الكسور مقبولة (8.5).';
        if (is_numeric($servings) && is_numeric($weight) && (float) $servings > 0 && (float) $weight > 0) {
            $hint .= ' الحصة الواحدة = '.Amount::short((float) $weight / (float) $servings, 0).' غ.';
        }

        return $hint;
    }

    private static function imageField(): FileUpload
    {
        return ImageUpload::apply(FileUpload::make('img'))
            ->label('صورة الطبق')
            ->disk('public')
            ->directory('products')
            ->imageEditor();
    }

    private static function officialAnalysis(): Section
    {
        return Section::make('القيم الرسمية المعتمدة')
            ->visible(fn ($record) => $record && $record->nutrientValues()->exists())
            ->collapsed()
            ->components([
                View::make('filament.clusters.brands.components.product-analysis-summary')
                    ->viewData(fn ($record) => ['product' => $record]),
            ]);
    }
}
