<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\RelationManagers;

use App\Data\Import\ReviewFlag;
use App\Enums\FoodSourceStatus;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use App\Models\ImportBatch;
use App\Services\Import\BatchReview;
use App\Services\Import\RecordReview;
use App\Support\ArabicSearch;
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
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use LogicException;

class FoodSourceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'foodSourceRecords';

    protected static ?string $modelLabel = 'سجل';

    protected static ?string $pluralModelLabel = 'السجلات';

    protected static ?string $title = 'سجلات هذه الدفعة';

    // نفس أهم 4 مغذيات نتحقق منها عند مراجعة القيم يدوياً
    private const PREVIEW_NUTRIENTS = ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g'];

    /** Filter values of "عرض". */
    private const SHOW_PENDING = 'pending';

    private const SHOW_FLAGGED = 'flagged';

    private const SHOW_CLEAN = 'clean';

    private ?BatchReview $batchReview = null;

    /** @var list<int>|null pending flagged ids of the batch, once per request */
    private ?array $flaggedIds = null;

    /** @var array{total:int, active:int, pending:int, flagged:int, rejected:int, other:int}|null */
    private ?array $progress = null;

    private function progress(): array
    {
        return $this->progress ??= $this->batchReview()->progress($this->batch());
    }

    /** After a review action the counts and flags are read again. */
    private function forget(): void
    {
        $this->flaggedIds = null;
        $this->progress = null;
    }

    private function batch(): ImportBatch
    {
        $batch = $this->getOwnerRecord();
        if (! $batch instanceof ImportBatch) {
            throw new LogicException('The review queue belongs to an import batch.');
        }

        return $batch;
    }

    private function batchReview(): BatchReview
    {
        return $this->batchReview ??= new BatchReview;
    }

    /** @return list<int> */
    private function flaggedIds(): array
    {
        return $this->flaggedIds ??= $this->batchReview()->pendingFlaggedIds($this->batch());
    }

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
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['food', 'foodForm', 'dataSource', 'nutrientValues.nutrient', 'reviewedBy', 'importBatch']);
                // Flagged records first: they are the ones that need a person.
                if ($ids = $this->flaggedIds()) {
                    $query->orderByRaw('case when food_source_records.id in ('.implode(',', array_map('intval', $ids)).') then 0 else 1 end');
                }
            })
            ->description(fn () => new HtmlString(view('filament.clusters.import-review.components.review-progress', [
                'progress' => $this->progress(),
            ])->render()))
            ->recordTitleAttribute('external_ref')
            ->columns([
                TextColumn::make('food.name_ar')
                    ->label('الغذاء')
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'food', fn (Builder $food) => ArabicSearch::constrain($food, ['name_ar', 'name_en'], $search),
                    )),
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
                    ->getStateUsing(fn ($record) => self::nutrientAmount($record, 'energy_kcal').' kcal'),
                TextColumn::make('preview_protein')
                    ->label('بروتين')
                    ->getStateUsing(fn ($record) => self::nutrientAmount($record, 'protein_g').' غ'),
                TextColumn::make('preview_fat')
                    ->label('دهون')
                    ->getStateUsing(fn ($record) => self::nutrientAmount($record, 'fat_g').' غ'),
                TextColumn::make('preview_carbs')
                    ->label('كربوهيدرات')
                    ->getStateUsing(fn ($record) => self::nutrientAmount($record, 'carbohydrate_g').' غ'),
                TextColumn::make('review_flags')
                    ->label('تنبيهات')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->placeholder('—')
                    ->getStateUsing(fn ($record) => array_map(fn (ReviewFlag $f) => $f->label, $this->batchReview()->flagsFor($record)))
                    ->tooltip(fn ($record) => implode("\n", array_map(fn (ReviewFlag $f) => $f->detail, $this->batchReview()->flagsFor($record))) ?: null)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('reviewedBy.name')
                    ->label('راجعه')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('nutrient_values_count')
                    ->label('كل القيم')
                    ->counts('nutrientValues')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('id')
            ->emptyStateHeading('لا توجد سجلات هنا')
            ->emptyStateDescription('غيّر «عرض» أو «الحالة» لرؤية السجلات المعتمدة والمرفوضة.')
            ->headerActions([
                Action::make('approveClean')
                    ->label(fn () => 'اعتمد كل السليم ('.$this->cleanCount().')')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize('reviewAny')
                    ->visible(fn () => $this->cleanCount() > 0)
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد كل السجلات السليمة')
                    ->modalDescription(fn () => sprintf(
                        'سيُعتمد %d سجلاً بلا أي تنبيه، من الدفعة كلها لا من هذه الصفحة فقط.%s',
                        $this->cleanCount(),
                        ($n = count($this->flaggedIds())) ? " السجلات الـ{$n} التي عليها تنبيه تبقى للمراجعة واحداً واحداً." : '',
                    ))
                    ->modalSubmitActionLabel('اعتمد')
                    ->action(function () {
                        $result = $this->batchReview()->approveCleanInBatch($this->batch(), auth()->user());
                        $this->forget();
                        Notification::make()
                            ->title("تم اعتماد {$result['approved']} سجلاً")
                            ->body($result['flagged'] ? "بقي {$result['flagged']} عليها تنبيه: افتح كل واحد منها." : null)
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('show')
                    ->label('عرض')
                    ->options([
                        self::SHOW_PENDING => 'كل الباقي (التنبيهات أولاً)',
                        self::SHOW_FLAGGED => 'باقٍ، فيه تنبيه',
                        self::SHOW_CLEAN => 'باقٍ، سليم',
                    ])
                    ->default(self::SHOW_PENDING)
                    ->query(function (Builder $query, array $data) {
                        $show = $data['value'] ?? null;
                        if (! in_array($show, [self::SHOW_PENDING, self::SHOW_FLAGGED, self::SHOW_CLEAN], true)) {
                            return;
                        }
                        $query->where('status', FoodSourceStatus::PendingReview->value);
                        match ($show) {
                            self::SHOW_FLAGGED => $query->whereKey($this->flaggedIds()),
                            self::SHOW_CLEAN => $query->whereKeyNot($this->flaggedIds()),
                            default => null,
                        };
                    }),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(FoodSourceStatus::class),
            ])
            ->recordActions([
                // Details in a side panel: the reviewer stays on the batch, and needs no
                // food-database rights to see what they are approving.
                Action::make('viewFullDetails')
                    ->label('كل القيم')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->slideOver()
                    ->modalHeading(fn ($record) => $record->food?->name_ar)
                    ->modalContent(fn ($record) => view('filament.clusters.import-review.components.record-details', ['record' => $record, 'flags' => $this->batchReview()->flagsFor($record)]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Action::make('openRecord')
                    ->label('فتح السجل')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->authorize('update')
                    ->url(fn ($record) => FoodSourceRecordResource::getUrl('edit', [
                        'food' => $record->food_id,
                        'record' => $record->id,
                    ]))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('اعتماد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // Hidden AND refused on the server for anyone without the review permission.
                    ->authorize('review')
                    ->requiresConfirmation()
                    ->modalHeading('اعتماد هذا السجل')
                    ->modalDescription(fn ($record) => $this->batchReview()->flagsFor($record)
                        ? new HtmlString('<strong>على هذا السجل تنبيه:</strong><br>'.implode('<br>', array_map(fn (ReviewFlag $f) => '• '.e($f->detail), $this->batchReview()->flagsFor($record))).'<br><br>اعتمده فقط إن راجعت الأرقام، واكتب السبب.')
                        : 'سيصبح هذا السجل نشطاً، ويُحتسب في نتائج النظام. هل أنت متأكد؟')
                    ->schema(fn ($record) => $this->batchReview()->flagsFor($record) ? [
                        Textarea::make('reason')
                            ->label('سبب الاعتماد رغم التنبيه')
                            ->helperText('مثال: «راجعت الملصق، الرقم صحيح». يُحفظ في ملاحظات السجل مع اسمك.')
                            ->required()
                            ->maxLength(RecordReview::MAX_REASON_LENGTH),
                    ] : [])
                    ->action(function ($record, array $data) {
                        try {
                            $this->batchReview()->approve($record, auth()->user(), $data['reason'] ?? null);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        $this->forget();
                        Notification::make()->title('تم الاعتماد')->success()->send();
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize('review')
                    ->requiresConfirmation()
                    ->modalHeading('رفض هذا السجل')
                    ->modalDescription('سيبقى السجل في القاعدة كسجل تاريخي، لكنه لن يُحتسب أبداً.')
                    ->form([
                        Textarea::make('reason')
                            ->label('سبب الرفض')
                            ->helperText('يُحفظ في ملاحظات السجل مع اسمك، ليعرف الفريق سبب الرفض.')
                            ->required()
                            ->maxLength(RecordReview::MAX_REASON_LENGTH),
                    ])
                    ->action(function ($record, array $data) {
                        app(RecordReview::class)->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('تم الرفض')->danger()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('اعتماد المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->authorize('reviewAny')
                        ->requiresConfirmation()
                        ->modalDescription('يُعتمد من المحدد كل سجل «قيد المراجعة» بلا تنبيه، وتُفعَّل أغذيته الجديدة. ما عليه تنبيه يبقى ليُعتمد وحده مع السبب.')
                        ->action(function (Collection $records) {
                            $result = $this->batchReview()->approveClean($records, auth()->user());
                            $this->forget();
                            Notification::make()
                                ->title("تم اعتماد {$result['approved']} سجلاً")
                                ->body($result['flagged'] ? "تُرك {$result['flagged']} لأن عليها تنبيهاً." : null)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    private function cleanCount(): int
    {
        $progress = $this->progress();

        return $progress['pending'] - $progress['flagged'];
    }

    private static function nutrientAmount($record, string $code): string
    {
        $value = $record->nutrientValues->first(fn ($v) => $v->nutrient?->code === $code);

        return $value?->amount_per_100g !== null ? number_format((float) $value->amount_per_100g, 1) : '—';
    }
}
