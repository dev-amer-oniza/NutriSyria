<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Tables;

use App\Enums\FoodSourceStatus;
use App\Enums\ImportBatchStatus;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'foodSourceRecords as pending_records_count' => fn (Builder $q) => $q->where('status', FoodSourceStatus::PendingReview->value),
            ]))
            ->columns([
                TextColumn::make('original_filename')
                    ->label('الملف')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('food_source_records_count')
                    ->label('عدد السجلات')
                    ->counts('foodSourceRecords')
                    ->badge()
                    ->color('gray'),
                // "180 / 243": approved out of all, green once nothing waits.
                TextColumn::make('active_records_count')
                    ->label('المراجعة')
                    ->counts(['foodSourceRecords as active_records_count' => fn (Builder $q) => $q->where('status', FoodSourceStatus::Active->value)])
                    ->formatStateUsing(fn ($state, $record) => "{$state} / {$record->food_source_records_count} معتمد")
                    ->description(fn ($record) => $record->pending_records_count ? "{$record->pending_records_count} باقٍ" : null)
                    ->badge()
                    ->color(fn ($record) => $record->food_source_records_count > 0 && ! $record->pending_records_count ? 'success' : 'warning'),
                TextColumn::make('user.name')
                    ->label('استوردها')
                    // no user: imported with php artisan import:bundle
                    ->placeholder('سطر الأوامر'),
                TextColumn::make('committed_at')
                    ->label('تاريخ الحفظ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(ImportBatchStatus::class),
                Filter::make('awaiting_review')
                    ->label('فيها سجلات باقية للمراجعة')
                    ->query(fn (Builder $query) => $query->whereHas('foodSourceRecords', fn (Builder $q) => $q->where('status', FoodSourceStatus::PendingReview->value))),
            ])
            ->recordActions([
                EditAction::make()->label('مراجعة'),
                DeleteAction::make(),
            ]);
    }
}
