<?php
namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DataSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray')
                    ->copyable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('priority')
                    ->label('الأولوية')
                    ->sortable()
                    ->badge()
                    ->color('success'),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
                TextColumn::make('food_source_records_count')
                    ->label('عدد السجلات المرتبطة')
                    ->counts('foodSourceRecords'),
            ])
            ->defaultSort('priority')
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
