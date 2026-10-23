<?php
namespace App\Filament\Clusters\Brands\Resources\Products\RelationManagers;

use App\Support\ArabicSearch;
use App\Enums\FoodSourceStatus;
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
use Illuminate\Validation\Rule;

class RecipeIngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipeIngredients';

    protected static ?string $modelLabel = 'مكوّن';

    protected static ?string $pluralModelLabel = 'المكوّنات';

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
                    // Approved records only: pending or rejected data never reaches a recipe.
                    ->options(fn () => self::ingredientOptions())
                    ->getSearchResultsUsing(fn (string $search) => self::ingredientOptions($search))
                    ->getOptionLabelUsing(function ($value) {
                        $r = FoodSourceRecord::with(['food', 'foodForm', 'dataSource'])->find($value);

                        return $r ? self::labelFor($r) : null;
                    })
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->presetPortion($get, $set))
                    // A searchable select accepts any id the browser sends: check it on the server.
                    ->rules([Rule::exists('food_source_records', 'id')->where('status', FoodSourceStatus::Active->value)])
                    ->required(),

                Select::make('measure_unit_id')
                    ->label('وحدة القياس')
                    ->relationship('measureUnit', 'name_ar')
                    ->arabicSearch()
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
                    ->rules(fn (Get $get) => [Rule::in(array_column($this->choicesFor($get), 'id'))])
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
                    // Never null: Filament shows only its "empty" tooltip on a null state, so the
                    // reason a line cannot be converted would be lost.
                    ->getStateUsing(function (Model $record) {
                        $grams = $this->conversionFor($record)->grams;

                        return $grams !== null ? number_format($grams, 1) . ' غ' : '⚠ غير متوفر';
                    })
                    ->badge()
                    ->color(fn(Model $record) => $this->conversionFor($record)->ok() ? 'success' : 'warning')
                    ->tooltip(fn(Model $record) => $this->conversionFor($record)->message ?? $this->conversionFor($record)->basis),
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

    /** The four values a record needs to be usable on its own (the owner's decision 2a). */
    private const CORE = ['energy_kcal' => 'السعرات', 'protein_g' => 'البروتين', 'fat_g' => 'الدهون', 'carbohydrate_g' => 'الكربوهيدرات'];

    /**
     * Approved records by food name, and within a food the most trusted source first
     * (data_sources.priority: Foundation before SR Legacy). A search matches the Arabic and
     * English names and the food's aliases, whatever the spelling ("ارز" finds "أرز").
     *
     * @return array<int, string>
     */
    private static function ingredientOptions(?string $search = null): array
    {
        return FoodSourceRecord::query()
            ->join('foods', 'foods.id', '=', 'food_source_records.food_id')
            ->join('data_sources', 'data_sources.id', '=', 'food_source_records.data_source_id')
            ->where('food_source_records.status', FoodSourceStatus::Active)
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => ArabicSearch::constrain($query, ['foods.name_ar', 'foods.name_en'], $search)
                ->orWhereHas('food.aliases', fn (Builder $alias) => ArabicSearch::constrain($alias, ['name_ar', 'name_en'], $search))))
            ->orderBy('foods.name_ar')
            ->orderBy('data_sources.priority')
            ->orderBy('food_source_records.id')
            ->select('food_source_records.*')
            ->with(['food', 'foodForm', 'dataSource', 'nutrientValues' => fn ($q) => $q
                ->whereHas('nutrient', fn (Builder $n) => $n->whereIn('code', array_keys(self::CORE)))
                ->with('nutrient:id,code')])
            ->limit(filled($search) ? 50 : 1000)
            ->get()
            ->mapWithKeys(fn (FoodSourceRecord $r) => [$r->id => self::labelFor($r)])
            ->all();
    }

    private static function labelFor(FoodSourceRecord $r): string
    {
        $label = sprintf('%s — %s (%s)', $r->food?->name_ar, $r->foodForm?->name_ar, $r->dataSource?->name);
        $missing = self::missingCore($r);

        return $missing ? $label.' · ناقص: '.implode('، ', $missing) : $label;
    }

    /**
     * Which of the four core values the record lacks. Missing calories alone do not count:
     * they are calculated from protein, fat and carbohydrate (NutrientGapFiller).
     *
     * @return list<string>
     */
    private static function missingCore(FoodSourceRecord $r): array
    {
        $r->loadMissing(['nutrientValues.nutrient:id,code']);
        $present = $r->nutrientValues->pluck('nutrient.code')->all();
        $missing = array_diff_key(self::CORE, array_flip($present));

        if (array_keys($missing) === ['energy_kcal']) {
            return [];
        }

        return array_values($missing);
    }

}
