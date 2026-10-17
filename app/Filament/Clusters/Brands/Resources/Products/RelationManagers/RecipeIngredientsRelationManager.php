<?php
namespace App\Filament\Clusters\Brands\Resources\Products\RelationManagers;

use App\Models\FoodSourceRecord;
use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\GramsConverter;
use App\Services\Nutrition\PortionResolver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class RecipeIngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipeIngredients';

    protected static ?string $title = 'مكونات الوصفة';

    public function getRelationship(): Relation
    {
        return $this->getOwnerRecord()->recipe->ingredients();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('food_source_record_id')
                    ->label('المكوّن')
                    ->options(fn() => FoodSourceRecord::query()
                            ->with(['food', 'foodForm', 'dataSource'])
                            ->orderByDesc('id')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn(FoodSourceRecord $r) => [
                                $r->id => self::labelFor($r),
                            ]))
                    ->getSearchResultsUsing(fn(string $search) => FoodSourceRecord::query()
                            ->whereHas('food', fn($q) => $q->where('name_ar', 'like', "%{$search}%"))
                            ->with(['food', 'foodForm', 'dataSource'])
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn(FoodSourceRecord $r) => [
                                $r->id => self::labelFor($r),
                            ]))
                    ->getOptionLabelUsing(function ($value) {
                        $r = FoodSourceRecord::with(['food', 'foodForm', 'dataSource'])->find($value);

                        return $r ? self::labelFor($r) : null;
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->presetPortion($get, $set))
                    ->required(),

                Select::make('measure_unit_id')
                    ->label('وحدة القياس')
                    ->relationship('measureUnit', 'name_ar')
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->presetPortion($get, $set))
                    ->preload()
                    ->native(false),

                // Shown only when the food has several sizes of this unit (small / medium / large).
                Select::make('food_portion_id')
                    ->label('حجم الحصة')
                    ->options(fn (Get $get) => collect($this->choicesFor($get))->pluck('label', 'id')->all())
                    ->visible(fn (Get $get) => $this->choicesFor($get) !== [])
                    // Required only when the food has no default size that settles it.
                    ->required(fn (Get $get) => $this->autoConversion($get)?->status === GramsConversion::AMBIGUOUS_PORTION)
                    ->helperText('للغذاء أكثر من حجم لهذه الوحدة: اختر الحجم المستعمل في هذه الوصفة.')
                    ->live()
                    ->native(false),

                TextInput::make('amount')
                    ->label('الكمية')
                    ->numeric()
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->required(),

                // تغذية راجعة فورية: يتحدّث عند أي تغيير في الحقول الثلاثة أعلاه.
                TextEntry::make('grams_preview')
                    ->label('الوزن الفعلي')
                    ->state(function (Get $get) {
                        if (! $get('food_source_record_id') || ! $get('measure_unit_id')) {
                            return '—';
                        }

                        $conversion = app(GramsConverter::class)->resolve(
                            (int) $get('food_source_record_id'), (int) $get('measure_unit_id'), (float) ($get('amount') ?? 0),
                            $get('food_portion_id') ? (int) $get('food_portion_id') : null,
                        );

                        if (! $conversion->ok()) {
                            return '⚠ '.$conversion->message;
                        }

                        return sprintf('= %s غرام', number_format($conversion->grams, 1))
                            .($conversion->basis ? " ({$conversion->basis})" : '');
                    })
                    ->columnSpanFull(),

                Toggle::make('is_added_after_cooking')
                    ->label('يُضاف بعد الطبخ')
                    ->helperText('مثال: زيت تزيين، ملح إضافي — لا يُحتسب في الوزن قبل الطبخ'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with([
                'ingredientRecord.food', 'ingredientRecord.foodForm', 'measureUnit', 'foodPortion',
            ]))
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('ingredientRecord.food.name_ar')
                    ->label('المكوّن')
                    ->weight('bold'),
                TextColumn::make('ingredientRecord.foodForm.name_ar')
                    ->label('الشكل')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('الكمية'),
                TextColumn::make('measureUnit.name_ar')
                    ->label('الوحدة')
                    ->formatStateUsing(function (?string $state, Model $record) {
                        $p = $record->foodPortion;
                        $size = $p ? PortionResolver::sizeNameAr($p->description ?? ($p->evidence['modifier'] ?? null)) : null;

                        return $size !== null ? "{$state} ({$size})" : $state;
                    })
                    ->badge(),
                TextColumn::make('grams')
                    ->label('الوزن بالغرام')
                    ->getStateUsing(function (Model $record) {
                        $grams = $this->conversionFor($record)->grams;

                        return $grams !== null ? number_format($grams, 1) . ' غ' : null;
                    })
                    ->badge()
                    ->color(fn(Model $record) => $this->conversionFor($record)->ok() ? 'success' : 'warning')
                    ->tooltip(fn(Model $record) => $this->conversionFor($record)->message ?? $this->conversionFor($record)->basis)
                    ->placeholder('⚠ غير متوفر'),
                TextColumn::make('is_added_after_cooking')
                    ->label('بعد الطبخ')
                    ->formatStateUsing(fn(bool $state) => $state ? 'نعم' : '—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()->label('إضافة مكوّن'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @var array<string, GramsConversion> one conversion per row and render, shared by state, color and tooltip */
    private array $conversions = [];

    private function conversionFor(Model $record): GramsConversion
    {
        $key = implode('|', [$record->getKey(), $record->food_source_record_id, $record->measure_unit_id, $record->amount, $record->food_portion_id]);

        return $this->conversions[$key] ??= app(GramsConverter::class)->resolve(
            (int) $record->food_source_record_id, (int) $record->measure_unit_id, (float) $record->amount,
            $record->food_portion_id !== null ? (int) $record->food_portion_id : null,
        );
    }

    /** @var array<string, list<array{id:int, label:string, grams:float}>> */
    private array $choices = [];

    /** @return list<array{id:int, label:string, grams:float}> */
    private function choicesFor(Get $get): array
    {
        [$recordId, $unitId] = [(int) $get('food_source_record_id'), (int) $get('measure_unit_id')];
        if (! $recordId || ! $unitId) {
            return [];
        }

        return $this->choices["{$recordId}|{$unitId}"] ??= app(GramsConverter::class)->choices($recordId, $unitId);
    }

    /** What the usual rules give without a chosen size (null until record and unit are set). */
    private function autoConversion(Get $get): ?GramsConversion
    {
        if (! $get('food_source_record_id') || ! $get('measure_unit_id')) {
            return null;
        }

        return app(GramsConverter::class)->resolve((int) $get('food_source_record_id'), (int) $get('measure_unit_id'), 1);
    }

    /** A new ingredient or unit starts on the size the usual rules pick (the default), or empty. */
    private function presetPortion(Get $get, Set $set): void
    {
        $ids = array_column($this->choicesFor($get), 'id');
        $auto = $ids ? $this->autoConversion($get)?->portionId : null;

        $set('food_portion_id', in_array($auto, $ids, true) ? $auto : null);
    }

    private static function labelFor(FoodSourceRecord $r): string
    {
        return sprintf('%s — %s (%s)', $r->food?->name_ar, $r->foodForm?->name_ar, $r->dataSource?->name);
    }

}
