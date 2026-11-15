<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers;

use App\Models\FoodPortion;
use App\Models\ImportBatch;
use App\Services\Import\BatchReview;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "حصص تحتاج قرار": the batch's foods whose portions have several weights in one unit and no
 * default (an egg is small, medium or large). A recipe line can still pick its size, so this
 * never blocks approving; marking a default once saves the choice on every line.
 * Only for who may edit portions (foods.manage).
 */
class PortionDecisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'foodSourceRecords';

    protected static ?string $title = 'حصص تحتاج قرار';

    protected static ?string $modelLabel = 'سجل';

    protected static ?string $pluralModelLabel = 'السجلات';

    /** @var array<int, list<array{unit_id:int, unit_name_ar:string, message:string, choices:list<array{id:int, label:string, grams:float}>}>>|null */
    private ?array $ambiguous = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && (bool) auth()->user()?->can('create', FoodPortion::class);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord instanceof ImportBatch ? count((new BatchReview)->ambiguousPortions($ownerRecord)) : 0;

        return $count ? (string) $count : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public static function isBadgeLazyLoaded(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    /** @return array<int, list<array{unit_id:int, unit_name_ar:string, message:string, choices:list<array{id:int, label:string, grams:float}>}>> */
    private function ambiguous(): array
    {
        $batch = $this->getOwnerRecord();

        return $this->ambiguous ??= $batch instanceof ImportBatch ? (new BatchReview)->ambiguousPortions($batch) : [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereKey(array_keys($this->ambiguous()))
                ->with(['food', 'foodForm', 'dataSource']))
            ->description('لهذه الأغذية أكثر من وزن لنفس الوحدة بلا حصة افتراضية. سطر الوصفة يختار الحجم بنفسه، فهذا لا يمنع الاعتماد؛ لكن الافتراضية تُختار سلفاً في كل وصفة.')
            ->columns([
                TextColumn::make('food.name_ar')
                    ->label('الغذاء')
                    ->weight('bold'),
                TextColumn::make('foodForm.name_ar')
                    ->label('الشكل')
                    ->badge(),
                TextColumn::make('dataSource.name')
                    ->label('المصدر')
                    ->toggleable(),
                TextColumn::make('ambiguous_units')
                    ->label('الوحدة')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn ($record) => array_column($this->ambiguous()[$record->id] ?? [], 'unit_name_ar'))
                    ->tooltip(fn ($record) => implode("\n", array_column($this->ambiguous()[$record->id] ?? [], 'message')) ?: null),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->emptyStateHeading('لا توجد حصص تنتظر قراراً')
            ->emptyStateDescription('كل أغذية هذه الدفعة لها وزن واحد لكل وحدة، أو حصة افتراضية.')
            ->recordActions([
                Action::make('chooseDefault')
                    ->label('اختر الافتراضية')
                    ->icon('heroicon-o-scale')
                    ->color('primary')
                    ->authorize(fn () => auth()->user()?->can('create', FoodPortion::class))
                    ->modalHeading(fn ($record) => 'الحصة الافتراضية: '.$record->food?->name_ar.($record->foodForm ? " ({$record->foodForm->name_ar})" : ''))
                    ->modalDescription('اختر الحجم الأكثر استعمالاً في المطاعم. يبقى كل سطر وصفة قادراً على اختيار حجم آخر.')
                    ->schema(fn ($record) => array_map(fn (array $unit) => Radio::make('unit_'.$unit['unit_id'])
                        ->label($unit['unit_name_ar'])
                        // The label already carries the weight: «medium» (1 حبة = 44 غ).
                        ->options(array_column($unit['choices'], 'label', 'id')), $this->ambiguous()[$record->id] ?? []))
                    ->modalSubmitActionLabel('احفظ')
                    ->action(function ($record, array $data) {
                        $saved = 0;
                        foreach ($this->ambiguous()[$record->id] ?? [] as $unit) {
                            $portionId = $data['unit_'.$unit['unit_id']] ?? null;
                            if ($portionId !== null && $portionId !== '') {
                                $saved += (int) (new BatchReview)->makeDefaultPortion($record, $unit['unit_id'], (int) $portionId);
                            }
                        }
                        $this->ambiguous = null;

                        $saved
                            ? Notification::make()->title('حُفظت الحصة الافتراضية')->success()->send()
                            : Notification::make()->title('لم يُختر شيء')->warning()->send();
                    }),
            ]);
    }
}
