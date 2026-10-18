<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Tables;

use App\Auth\BrandScope;
use App\Enums\NutritionApprovalStatus;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Support\Placeholders;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => NutritionApprovalStatus::withApprovalData($query))
            // Filament would open the read-only page first; whoever may edit goes straight to editing.
            ->recordUrl(fn ($record) => ProductResource::pageUrlFor($record))
            ->columns([
                ImageColumn::make('img')
                    ->disk('public')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(Placeholders::image('product')),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('brand.name')
                    ->label('البراند')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('nutrition_status')
                    ->label('القيم الغذائية')
                    ->state(fn ($record) => NutritionApprovalStatus::of($record))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('brand_id')
                    ->label('البراند')
                    ->relationship('brand', 'name', modifyQueryUsing: fn ($query) => BrandScope::brands($query, readOnly: true))
                    ->searchable(),
                Filter::make('awaiting')
                    ->label('تنتظر اعتماد الأخصائي')
                    ->query(fn (Builder $query) => $query->has('recipeIngredients')->doesntHave('nutrientValues')),
            ])
            ->recordActions([
                // Read-only for whoever may see but not change the product (the nutritionist).
                ViewAction::make()->hidden(fn ($record) => auth()->user()?->can('update', $record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
