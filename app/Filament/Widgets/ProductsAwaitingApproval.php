<?php

namespace App\Filament\Widgets;

use App\Auth\BrandScope;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Products whose official nutrition values wait for the nutritionist: none approved yet, or
 * the recipe changed after the approval. The nutritionist goes straight to the analysis;
 * a brand owner sees what is still waiting.
 */
class ProductsAwaitingApproval extends TableWidget
{
    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Product::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('منتجات تنتظر اعتماد القيم الغذائية')
            ->query(fn () => self::query())
            ->columns([
                TextColumn::make('name')->label('المنتج')->weight('bold'),
                TextColumn::make('brand.name')->label('البراند')->badge()->color('gray'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->state(fn (Product $record) => NutritionApprovalStatus::of($record))
                    ->badge(),
                TextColumn::make('recipe_ingredients_count')->label('المكوّنات')->badge()->color('gray'),
                TextColumn::make('updated_at')->label('آخر تعديل')->since(),
            ])
            ->recordUrl(fn (Product $record) => auth()->user()?->can('approveNutrition', $record)
                ? RecipeAnalysis::getUrl().'?product='.$record->id
                : ProductResource::pageUrlFor($record))
            ->emptyStateHeading('لا شيء ينتظر')
            ->emptyStateDescription('كل المنتجات التي لها وصفة معتمدة قيمها، ولم تتغير بعد الاعتماد.')
            ->defaultSort('updated_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /** Has a recipe, and either no official values or a recipe changed after their approval. */
    private static function query(): Builder
    {
        $changedAfterApproval = fn (Builder $recipe) => $recipe
            ->whereNotNull('product_recipes.nutrition_approved_at')
            ->where(fn (Builder $q) => $q
                ->whereColumn('product_recipes.updated_at', '>', 'product_recipes.nutrition_approved_at')
                ->orWhereExists(fn ($line) => $line->selectRaw('1')
                    ->from('product_recipe_ingredients')
                    ->whereColumn('product_recipe_ingredients.product_recipe_id', 'product_recipes.id')
                    ->whereColumn('product_recipe_ingredients.updated_at', '>', 'product_recipes.nutrition_approved_at')));

        return BrandScope::products(NutritionApprovalStatus::withApprovalData(Product::query()->with('brand')))
            ->has('recipeIngredients')
            ->where(fn (Builder $q) => $q
                ->doesntHave('nutrientValues')
                ->orWhereHas('recipe', $changedAfterApproval));
    }
}
