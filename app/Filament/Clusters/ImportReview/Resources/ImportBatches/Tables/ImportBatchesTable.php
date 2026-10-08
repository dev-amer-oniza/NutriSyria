<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label('الملف')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('foodSourceRecords_count')
                    ->label('عدد السجلات')
                    ->counts('foodSourceRecords')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('user.name')
                    ->label('استوردها')
                    ->placeholder('—'),
                TextColumn::make('committed_at')
                    ->label('تاريخ الحفظ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(\App\Enums\ImportBatchStatus::class),
            ])
            ->recordActions([
                EditAction::make()->label('مراجعة'),
                DeleteAction::make(),
            ]);
    }
}
