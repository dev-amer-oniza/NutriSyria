<?php
namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers;

use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use App\Models\Nutrient;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class FoodSourceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'foodSourceRecords';

    protected static ?string $title = 'سجلات هذه الدفعة';

    // نفس أهم 4 مغذيات نتحقق منها عند مراجعة القيم يدوياً
    private const PREVIEW_NUTRIENTS = ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g'];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('notes')
                    ->label('سبب الرفض / ملاحظة')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with([
                'food', 'foodForm', 'dataSource',
                'nutrientValues.nutrient',
            ]))
            ->recordTitleAttribute('external_ref')
            ->columns([
                TextColumn::make('food.name_ar')
                    ->label('الغذاء')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('foodForm.name_ar')
                    ->label('الشكل')
                    ->badge(),
                TextColumn::make('dataSource.name')
                    ->label('المصدر')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('external_ref')
                    ->label('الرقم المرجعي')
                    ->toggleable(),
                // أهم 4 قيم تظهر مباشرة في الجدول، بلا فتح أي شيء
                TextColumn::make('preview_energy')
                    ->label('طاقة')
                    ->getStateUsing(fn($record) => self::nutrientAmount($record, 'energy_kcal') . ' kcal'),
                TextColumn::make('preview_protein')
                    ->label('بروتين')
                    ->getStateUsing(fn($record) => self::nutrientAmount($record, 'protein_g') . ' غ'),
                TextColumn::make('preview_fat')
                    ->label('دهون')
                    ->getStateUsing(fn($record) => self::nutrientAmount($record, 'fat_g') . ' غ'),
                TextColumn::make('preview_carbs')
                    ->label('كربوهيدرات')
                    ->getStateUsing(fn($record) => self::nutrientAmount($record, 'carbohydrate_g') . ' غ'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('nutrient_values_count')
                    ->label('كل القيم')
                    ->counts('nutrientValues')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(FoodSourceStatus::class)
                    ->default(FoodSourceStatus::PendingReview->value),
            ])
            ->recordActions([
                Action::make('viewFullDetails')
                    ->label('كل القيم')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => FoodSourceRecordResource::getUrl('edit', [
                        'food' => $record->food_id,
                        'record' => $record->id,
                    ]))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('اعتماد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === FoodSourceStatus::PendingReview)
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد هذا السجل')
                    ->modalDescription('سيصبح هذا السجل نشطاً، ويُحتسب في نتائج النظام. هل أنت متأكد؟')
                    ->action(function ($record) {
                        $record->update(['status' => FoodSourceStatus::Active]);
                        if (! $record->food->is_active) {
                            $record->food->update(['is_active' => true]);
                        }
                        Notification::make()->title('تم الاعتماد')->success()->send();
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === FoodSourceStatus::PendingReview)
                    ->requiresConfirmation()
                    ->modalHeading('رفض هذا السجل')
                    ->modalDescription('سيبقى السجل في القاعدة كسجل تاريخي، لكنه لن يُحتسب أبداً.')
                    ->form([
                        Textarea::make('reason')->label('سبب الرفض')->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status' => FoodSourceStatus::Rejected,
                            'notes'  => trim(($record->notes ? $record->notes . "\n" : '') . "رُفض: {$data['reason']}"),
                        ]);
                        Notification::make()->title('تم الرفض')->danger()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('اعتماد المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalDescription('سيُعتمد كل سجل محدد بحالة "قيد المراجعة" فقط، وتُفعَّل أغذيته الجديدة.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            $records->where('status', FoodSourceStatus::PendingReview)->each(function (Model $record) use (&$count) {
                                $record->update(['status' => FoodSourceStatus::Active]);
                                if (! $record->food->is_active) {
                                    $record->food->update(['is_active' => true]);
                                }
                                $count++;
                            });
                            Notification::make()->title("تم اعتماد {$count} سجلاً")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private static function nutrientAmount($record, string $code): string
    {
        $value = $record->nutrientValues->first(fn($v) => $v->nutrient?->code === $code);

        return $value?->amount_per_100g !== null ? number_format((float) $value->amount_per_100g, 1) : '—';
    }
}
