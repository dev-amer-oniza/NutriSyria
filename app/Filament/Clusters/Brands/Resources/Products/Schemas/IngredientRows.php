<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Schemas;

use App\Auth\Access;
use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
use App\Models\MeasureUnit;
use App\Models\Preparation;
use App\Models\Product;
use App\Services\Import\ArabicNormalizer;
use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\GramsConverter;
use App\Support\ArabicSearch;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * "② شو فيه؟": the recipe's ingredients typed on the product page itself, one row each, with
 * the grams shown as soon as a row is complete (the owner's request: no separate table to find
 * after saving, no window per ingredient). Shared by restaurant products and kitchen dishes.
 *
 * The rules are the ones the old ingredients table applied: approved records only (checked on
 * the server), a size asked only when the food has several for that unit (PortionResolver).
 */
class IngredientRows
{
    /** The four values a record needs to be usable on its own (the owner's decision 2a). */
    private const CORE = ['energy_kcal' => 'السعرات', 'protein_g' => 'البروتين', 'fat_g' => 'الدهون', 'carbohydrate_g' => 'الكربوهيدرات'];

    public static function repeater(): Repeater
    {
        return Repeater::make('ingredients')
            ->hiddenLabel()
            ->relationship('ingredients')
            ->orderColumn('sort_order')
            // One line per ingredient, like a shopping list: easy to scan, no card per row.
            ->table([
                TableColumn::make('المكوّن')->markAsRequired()->width('34%'),
                TableColumn::make('الكمية')->markAsRequired()->width('11%'),
                TableColumn::make('الوحدة')->markAsRequired()->width('16%'),
                TableColumn::make('أي حجم؟')->width('17%'),
                TableColumn::make('الوزن')->width('12%'),
                TableColumn::make('على الوجه')->width('10%'),
            ])
            ->schema(self::fields())
            // A new dish opens on its first row. A row left completely empty is not saved, so a
            // dish can be kept before its recipe is written; a started row asks for the rest.
            ->defaultItems(1)
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => self::isBlank($data) ? null : self::toColumns($data))
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::toColumns($data))
            // A preparation line shows in the same field as a food: "p12" (كريمة تمر · تحضيري).
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => $data['preparation_id'] ?? null
                ? [...$data, 'food_source_record_id' => self::PREPARATION.$data['preparation_id']]
                : $data)
            ->addActionLabel('أضف مكوّناً')
            // Narrow screens (a phone): a compact two-column card per row (theme.css).
            ->extraAttributes(['class' => 'ns-recipe-rows'])
            ->reorderable()
            ->live();
    }

    /**
     * A preparation's own foods (تحضيراتي): the same rows, without "على الوجه" and without other
     * preparations in them (one level only, the owner's decision).
     */
    public static function preparationRepeater(): Repeater
    {
        return Repeater::make('ingredients')
            ->hiddenLabel()
            ->relationship('ingredients')
            ->orderColumn('sort_order')
            ->table([
                TableColumn::make('المكوّن')->markAsRequired()->width('40%'),
                TableColumn::make('الكمية')->markAsRequired()->width('14%'),
                TableColumn::make('الوحدة')->markAsRequired()->width('18%'),
                TableColumn::make('أي حجم؟')->width('16%'),
                TableColumn::make('الوزن')->width('12%'),
            ])
            ->schema(array_slice(self::fields(withPreparations: false), 0, 5))
            ->defaultItems(1)
            ->minItems(1)
            ->addActionLabel('أضف مكوّناً')
            ->extraAttributes(['class' => 'ns-recipe-rows'])
            ->reorderable()
            ->live();
    }

    /** A preparation's value in the ingredient field: "p12". */
    public const PREPARATION = 'p';

    /** The preparation id behind a field value ("p12" → 12), or null for a food record. */
    public static function preparationId(mixed $value): ?int
    {
        return is_string($value) && str_starts_with($value, self::PREPARATION) && ctype_digit(substr($value, 1))
            ? (int) substr($value, 1) : null;
    }

    /** @param  array<string, mixed>  $data  the row as typed → the columns it is saved in */
    private static function toColumns(array $data): array
    {
        $preparation = self::preparationId($data['food_source_record_id'] ?? null);

        return [...$data,
            'preparation_id' => $preparation,
            'food_source_record_id' => $preparation ? null : $data['food_source_record_id'],
            'food_portion_id' => $preparation ? null : ($data['food_portion_id'] ?? null),
        ];
    }

    /** @return list<Component|Field> */
    public static function fields(bool $withPreparations = true): array
    {
        return [
            Select::make('food_source_record_id')
                ->label('المكوّن')
                ->placeholder('اكتب اسمه: عدس، بصل، زيت زيتون…')
                // Approved records only: pending or rejected data never reaches a recipe. The
                // brand's own preparations first (كريمة تمر · تحضيري).
                // The same list for every row: built once per request, not once per row.
                ->options(fn ($livewire) => ($withPreparations ? self::preparationOptions($livewire) : [])
                    + self::memo('ingredient_options', fn () => self::options()))
                ->getSearchResultsUsing(fn (string $search, $livewire) => ($withPreparations ? self::preparationOptions($livewire, $search) : [])
                    + self::options($search))
                ->getOptionLabelUsing(fn ($value, $livewire) => self::preparationId($value) !== null
                    ? (self::preparationOptions($livewire)[$value] ?? null)
                    : (self::memo('ingredient_options', fn () => self::options())[(int) $value]
                    ?? self::memo("ingredient_label.{$value}", function () use ($value) {
                        $r = FoodSourceRecord::with(['food', 'foodForm', 'dataSource'])->find($value);

                        return $r ? self::labelFor($r) : null;
                    })))
                ->searchable()
                // Always open below the field: flipping above it on a phone moved the search box,
                // and the page jumped when the keyboard came up (the owner's report).
                ->position('bottom')
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::presetPortion($get, $set))
                // A searchable select accepts any id the browser sends: check it on the server.
                ->rules(fn ($livewire) => [function (string $attribute, mixed $value, \Closure $fail) use ($livewire, $withPreparations): void {
                    $preparation = self::preparationId($value);
                    $ok = $preparation !== null
                        ? $withPreparations && array_key_exists(self::PREPARATION.$preparation, self::preparationOptions($livewire))
                        : FoodSourceRecord::query()->whereKey((int) $value)->where('status', FoodSourceStatus::Active)->exists();
                    if (! $ok) {
                        $fail('اختار مكوّناً من القائمة.');
                    }
                }])
                ->required(fn (Get $get) => self::started($get))
                ->columnSpan(['default' => 12, 'md' => 5]),

            TextInput::make('amount')
                ->label('الكمية')
                ->numeric()
                ->minValue(0)
                // The number keypad with a decimal point on phones (½ كوب = 0.5).
                ->inputMode('decimal')
                ->live(onBlur: true)
                ->required(fn (Get $get) => self::started($get))
                ->columnSpan(['default' => 6, 'md' => 2]),

            Select::make('measure_unit_id')
                ->label('الوحدة')
                // The units, loaded once for all rows (a relationship select reloaded them per row).
                ->options(fn () => self::units())
                ->getSearchResultsUsing(fn (string $search) => ArabicSearch::constrain(MeasureUnit::query(), ['name_ar'], $search)
                    ->orderBy('name_ar')->limit(50)->pluck('name_ar', 'id')->all())
                ->getOptionLabelUsing(fn ($value) => self::units()[(int) $value] ?? null)
                ->rules([Rule::exists('measure_units', 'id')])
                ->required(fn (Get $get) => self::started($get))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::presetPortion($get, $set))
                ->native(false)
                ->columnSpan(['default' => 6, 'md' => 2]),

            // Shown only when the food has several sizes of this unit (small / medium / large).
            Select::make('food_portion_id')
                ->label('أي حجم؟')
                ->placeholder('اختار الحجم')
                ->options(fn (Get $get) => collect(self::choicesFor($get))->pluck('label', 'id')->all())
                ->visible(fn (Get $get) => self::choicesFor($get) !== [])
                // Required only when the food has no default size that settles it.
                ->required(fn (Get $get) => self::autoConversion($get)?->status === GramsConversion::AMBIGUOUS_PORTION)
                ->rules(fn (Get $get) => [Rule::in(array_column(self::choicesFor($get), 'id'))])
                ->live()
                ->native(false)
                ->columnSpan(['default' => 12, 'md' => 5]),

            // The answer, right on the row: "= 400 غ" or why it cannot be weighed.
            TextEntry::make('grams_preview')
                ->label('الوزن')
                ->state(fn (Get $get) => self::gramsText($get))
                ->color(fn (Get $get) => str_starts_with(self::gramsText($get), '=') ? 'success' : 'warning')
                // The engine's full reason, for whoever wants it; the row keeps a short line.
                ->tooltip(fn (Get $get) => ($c = self::conversion($get)) && ! $c->ok() ? $c->message : null)
                ->weight('bold')
                ->columnSpan(['default' => 12, 'md' => 3]),

            Toggle::make('is_added_after_cooking')
                ->label('على الوجه')
                ->columnSpan(['default' => 12, 'md' => 7]),
        ];
    }

    /** @return array<int, float> grams per unit, for the weight units only (g, kg…) */
    private static function massFactors(): array
    {
        return self::memo('ingredient_mass_units', fn () => MeasureUnit::query()->where('dimension', 'mass')
            ->whereNotNull('base_factor')->pluck('base_factor', 'id')->map(fn ($f) => (float) $f)->all());
    }

    /**
     * The brand's own preparations, "كريمة تمر · تحضيري" by "p12", for the product being
     * written (its brand field, or the product itself). Only those the user may write with.
     *
     * @return array<string, string>
     */
    public static function preparationOptions(mixed $livewire, ?string $search = null): array
    {
        $brandId = (int) (data_get($livewire, 'data.brand_id')
            ?: (method_exists($livewire, 'getRecord') && $livewire->getRecord() instanceof Product ? $livewire->getRecord()->brand_id : 0));
        if (! $brandId) {
            return [];
        }

        $all = self::memo("ingredient_preparations.{$brandId}", function () use ($brandId) {
            $user = auth()->user();
            if (! $user || ! $user->can('create', Preparation::class)
                || ! ($user->allows(Access::BRANDS_MANAGE_ALL) || $user->managesBrand($brandId))) {
                return [];
            }

            return Preparation::query()->where('brand_id', $brandId)->orderBy('name')->pluck('name', 'id')
                ->mapWithKeys(fn (string $name, int $id) => [self::PREPARATION.$id => $name.' · تحضيري'])->all();
        });

        return blank($search) ? $all : array_filter($all, fn (string $label) => str_contains(ArabicNormalizer::normalize($label), ArabicNormalizer::normalize($search)));
    }

    /** @return array<int, string> every unit by its Arabic name, once per request */
    private static function units(): array
    {
        return self::memo('ingredient_units', fn () => MeasureUnit::query()->orderBy('name_ar')->pluck('name_ar', 'id')->all());
    }

    /** A row the owner began: its ingredient, amount or unit is filled. */
    private static function started(Get $get): bool
    {
        return ! self::isBlank([
            'food_source_record_id' => $get('food_source_record_id'),
            'amount' => $get('amount'),
            'measure_unit_id' => $get('measure_unit_id'),
        ]);
    }

    /** @param  array<string, mixed>  $row */
    private static function isBlank(array $row): bool
    {
        return blank($row['food_source_record_id'] ?? null) && blank($row['amount'] ?? null) && blank($row['measure_unit_id'] ?? null);
    }

    /** "= 400 غ" for a complete row, "⚠ …" with the reason when it cannot be weighed, "—" before. */
    public static function gramsText(Get $get): string
    {
        $conversion = self::conversion($get);
        if ($conversion === null) {
            return '—';
        }

        if ($conversion->ok()) {
            return '= '.number_format((float) $conversion->grams, $conversion->grams < 10 ? 1 : 0).' غ';
        }

        // One short line the owner can act on; the full reason is the tooltip.
        return match ($conversion->status) {
            GramsConversion::AMBIGUOUS_PORTION => self::choicesFor($get) !== [] ? '→ اختار الحجم' : '⚠ اكتبه بالغرام',
            GramsConversion::INVALID_AMOUNT => '⚠ اكتب كمية أكبر من صفر',
            default => '⚠ ما منعرف وزنها، اكتبه بالغرام',
        };
    }

    private static function conversion(Get $get): ?GramsConversion
    {
        if (! $get('food_source_record_id') || ! $get('measure_unit_id') || (float) $get('amount') <= 0) {
            return null;
        }
        // A preparation is weighed in grams or kilos only (the owner's decision 3a).
        if (self::preparationId($get('food_source_record_id')) !== null) {
            $factor = self::massFactors()[(int) $get('measure_unit_id')] ?? null;

            return $factor !== null
                ? GramsConversion::converted((float) $get('amount') * $factor, GramsConversion::METHOD_MASS)
                : GramsConversion::failed(GramsConversion::UNKNOWN_UNIT, 'التحضير بينكتب بالغرام أو الكيلو.');
        }
        // Asked for the weight, its colour and its tooltip: worked out once per row and request.
        $args = [(int) $get('food_source_record_id'), (int) $get('measure_unit_id'), (float) $get('amount'),
            $get('food_portion_id') ? (int) $get('food_portion_id') : null];

        return self::memo('ingredient_grams.'.implode('|', $args), fn () => app(GramsConverter::class)->resolve(...$args));
    }

    /** @return list<array{id:int, label:string, grams:float}> */
    private static function choicesFor(Get $get): array
    {
        [$recordId, $unitId] = [(int) $get('food_source_record_id'), (int) $get('measure_unit_id')];
        if (! $recordId || ! $unitId || self::preparationId($get('food_source_record_id')) !== null) {
            return [];
        }

        return self::memo("ingredient_choices.{$recordId}|{$unitId}", fn () => app(GramsConverter::class)->choices($recordId, $unitId));
    }

    /**
     * A value worked out once per request: a form asks for the same list or weight many times
     * while it renders (every row, every field of a row). Kept on the request itself, so never
     * across requests or tests.
     *
     * @template T
     *
     * @param  \Closure(): T  $make
     * @return T
     */
    private static function memo(string $key, \Closure $make): mixed
    {
        $memo = request()->attributes;
        if (! $memo->has($key)) {
            $memo->set($key, $make());
        }

        return $memo->get($key);
    }

    /** What the usual rules give without a chosen size (null until record and unit are set). */
    private static function autoConversion(Get $get): ?GramsConversion
    {
        if (! $get('food_source_record_id') || ! $get('measure_unit_id') || self::preparationId($get('food_source_record_id')) !== null) {
            return null;
        }

        [$recordId, $unitId] = [(int) $get('food_source_record_id'), (int) $get('measure_unit_id')];

        return self::memo("ingredient_auto.{$recordId}|{$unitId}", fn () => app(GramsConverter::class)->resolve($recordId, $unitId, 1));
    }

    /** A new ingredient or unit starts on the size the usual rules pick (the default), or empty. */
    private static function presetPortion(Get $get, Set $set): void
    {
        $ids = array_column(self::choicesFor($get), 'id');
        $auto = $ids ? self::autoConversion($get)?->portionId : null;

        $set('food_portion_id', in_array($auto, $ids, true) ? $auto : null);
    }

    /**
     * Approved records by food name, and within a food the most trusted source first
     * (data_sources.priority: Foundation before SR Legacy). A search matches the Arabic and
     * English names and the food's aliases, whatever the spelling ("ارز" finds "أرز").
     *
     * @return array<int, string>
     */
    public static function options(?string $search = null): array
    {
        $options = [];
        $records = FoodSourceRecord::query()
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
            ->get();

        // The source is named only where two records share a food and form (Foundation, SR Legacy).
        $shared = [];
        foreach ($records as $record) {
            $key = $record->getAttribute('food_id').'|'.$record->getAttribute('food_form_id');
            $shared[$key] = ($shared[$key] ?? 0) + 1;
        }
        foreach ($records as $record) {
            $key = $record->getAttribute('food_id').'|'.$record->getAttribute('food_form_id');
            $options[(int) $record->getKey()] = self::labelFor($record, $shared[$key] > 1);
        }

        return $options;
    }

    /** "عدس — نيء", with its source when another record shares the food and form. */
    public static function labelFor(FoodSourceRecord $r, bool $withSource = false): string
    {
        $label = $r->foodForm ? "{$r->food?->name_ar} — {$r->foodForm->name_ar}" : (string) $r->food?->name_ar;
        if ($withSource) {
            $label .= ' ('.self::shortSource((string) $r->dataSource?->name).')';
        }
        $missing = self::missingCore($r);

        return $missing ? $label.' · ناقص: '.implode('، ', $missing) : $label;
    }

    /** "USDA Foundation Foods" → "Foundation": the label fits one line on a phone. */
    private static function shortSource(string $name): string
    {
        $short = trim((string) preg_replace('/^USDA\s+|\s+Foods$/u', '', $name));

        return $short !== '' ? $short : $name;
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
