<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductRecipesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('servings')
                    ->label('عدد الحصص'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('weight_before_cooking_g')
                    ->label('الوزن قبل الطبخ')
                    ->suffix(' غ')
                    ->placeholder('—'),
                TextColumn::make('weight_after_cooking_g')
                    ->label('الوزن بعد الطبخ')
                    ->suffix(' غ')
                    ->placeholder('—'),
                TextColumn::make('ingredients_count')
                    ->label('عدد المكونات')
                    ->counts('ingredients')
                    ->badge()
                    ->color('gray'),
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
