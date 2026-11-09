<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Tables;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Support\ArabicSearch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Support\Placeholders;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // An owner not yet linked to their restaurant is told why the list is empty (decision 2a).
            ->emptyStateHeading(fn () => BrandResource::ownedBrandIds() === [] ? 'لم يُربط حسابك بمطعمك بعد' : null)
            ->emptyStateDescription(fn () => BrandResource::ownedBrandIds() === []
                ? 'تربط إدارة NutriSyria حسابك بمطعمك بعد مراجعة طلبك، ثم تجده هنا مع منتجاته وتصنيفاته.'
                : null)
            ->emptyStateIcon(fn () => BrandResource::ownedBrandIds() === [] ? 'heroicon-o-clock' : null)
            ->columns([
                ImageColumn::make('logo')
                    ->disk('public')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(Placeholders::image('brand')),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(query: ArabicSearch::column('name'))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    // Products are not the admin's (the owner's decision 2a).
                    ->hidden(fn () => (bool) auth()->user()?->isSuperAdmin())
                    ->counts('products')
                    ->badge()
                    ->color('success'),
                TextColumn::make('phone_1')
                    ->label('الهاتف')
                    ->placeholder('—'),
                IconColumn::make('has_delivery')
                    ->label('توصيل')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
