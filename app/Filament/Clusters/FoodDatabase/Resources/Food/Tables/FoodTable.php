<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Tables;

use App\Support\ArabicSearch;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use App\Enums\Allergen;
use App\Models\Food;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Collection;
use App\Filament\Support\Placeholders;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FoodTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('img')
                    ->disk('public')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(Placeholders::image('food')),
                TextColumn::make('name_ar')
                    ->label('الاسم بالعربية')
                    ->searchable(query: ArabicSearch::column('name_ar'))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('name_en')
                    ->label('الاسم بالإنجليزية')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name_ar')
                    ->label('التصنيف')
                    ->badge()
                    ->color('success'),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                TextColumn::make('allergens')
                    ->label('مسبّبات الحساسية')
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn (string $state) => Allergen::tryFrom($state)?->label() ?? $state)
                    ->placeholder(fn (Food $record) => $record->allergens_checked ? 'لا شيء' : 'لم تُفحص')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name_ar')
            ->filters([
                SelectFilter::make('food_category_id')
                    ->label('التصنيف')
                    ->relationship('category', 'name_ar')
                    ->searchable(),
                TernaryFilter::make('allergens_checked')
                    ->label('فحص الحساسية')
                    ->placeholder('الكل')
                    ->trueLabel('مفحوصة')
                    ->falseLabel('لم تُفحص بعد'),
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
                    BulkAction::make('noAllergens')
                        ->label('تحققتُ: بلا مسبّبات حساسية')
                        ->icon('heroicon-o-shield-check')
                        ->authorizeIndividualRecords('update')
                        ->requiresConfirmation()
                        ->modalDescription('تُعلَّم الأغذية المحددة مفحوصة وخالية من مسبّبات الحساسية.')
                        ->action(fn (Collection $records) => $records->each->update(['allergens' => [], 'allergens_checked' => true]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
