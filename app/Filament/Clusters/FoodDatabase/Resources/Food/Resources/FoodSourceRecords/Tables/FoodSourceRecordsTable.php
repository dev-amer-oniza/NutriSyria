<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FoodSourceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('foodForm.name_ar')
                    ->label('الشكل')
                    ->badge(),
                TextColumn::make('dataSource.name')
                    ->label('المصدر')
                    ->wrap(),
                TextColumn::make('external_ref')
                    ->label('الرقم المرجعي')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                IconColumn::make('is_preferred')
                    ->label('مفضّل')
                    ->boolean(),
                TextColumn::make('nutrient_values_count')
                    ->label('عدد القيم الغذائية')
                    ->counts('nutrientValues')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('id', 'desc')
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
