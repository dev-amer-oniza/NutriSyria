<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Schemas;

use App\Enums\FoodSourceStatus;
use App\Models\FoodSourceRecord;
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
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => self::isBlank($data) ? null : $data)
            ->addActionLabel('أضف مكوّناً')
            ->reorderable()
            ->live();
    }

    /** @return list<Component|Field> */
    public static function fields(): array
    {
        return [
            Select::make('food_source_record_id')
                ->label('المكوّن')
                ->placeholder('اكتب اسمه: عدس، بصل، زيت زيتون…')
                // Approved records only: pending or rejected data never reaches a recipe.
                ->options(fn () => self::options())
                ->getSearchResultsUsing(fn (string $search) => self::options($search))
                ->getOptionLabelUsing(function ($value) {
                    $r = FoodSourceRecord::with(['food', 'foodForm', 'dataSource'])->find($value);

                    return $r ? self::labelFor($r) : null;
                })
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::presetPortion($get, $set))
                // A searchable select accepts any id the browser sends: check it on the server.
                ->rules([Rule::exists('food_source_records', 'id')->where('status', FoodSourceStatus::Active->value)])
                ->required(fn (Get $get) => self::started($get))
                ->columnSpan(['default' => 12, 'md' => 5]),

            TextInput::make('amount')
                ->label('الكمية')
                ->numeric()
                ->minValue(0)
                ->live(onBlur: true)
                ->required(fn (Get $get) => self::started($get))
                ->columnSpan(['default' => 6, 'md' => 2]),

            Select::make('measure_unit_id')
                ->label('الوحدة')
                ->relationship('measureUnit', 'name_ar')
                ->arabicSearch()
                ->required(fn (Get $get) => self::started($get))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::presetPortion($get, $set))
                ->native(false)
                ->columnSpan(['default' => 6, 'md' => 2]),

            // Shown only when the food has several sizes of this unit (small / medium / large).
            Select::make('food_portion_id')
                ->label('أي حجم؟')
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
                ->color(fn (Get $get) => str_starts_with(self::gramsText($get), '⚠') ? 'warning' : 'success')
                ->weight('bold')
                ->columnSpan(['default' => 12, 'md' => 3]),

            Toggle::make('is_added_after_cooking')
                ->label('على الوجه')
                ->columnSpan(['default' => 12, 'md' => 7]),
        ];
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

        return $conversion->ok()
            ? '= '.number_format((float) $conversion->grams, $conversion->grams < 10 ? 1 : 0).' غ'
            : '⚠ '.$conversion->message;
    }

    private static function conversion(Get $get): ?GramsConversion
    {
        if (! $get('food_source_record_id') || ! $get('measure_unit_id') || (float) $get('amount') <= 0) {
            return null;
        }

        return app(GramsConverter::class)->resolve(
            (int) $get('food_source_record_id'), (int) $get('measure_unit_id'), (float) $get('amount'),
            $get('food_portion_id') ? (int) $get('food_portion_id') : null,
        );
    }

    /** @return list<array{id:int, label:string, grams:float}> */
    private static function choicesFor(Get $get): array
    {
        [$recordId, $unitId] = [(int) $get('food_source_record_id'), (int) $get('measure_unit_id')];
        if (! $recordId || ! $unitId) {
            return [];
        }

        // Kept for one request only (on the request itself), never across requests or tests.
        $memo = request()->attributes;
        $key = "ingredient_choices.{$recordId}|{$unitId}";
        if (! $memo->has($key)) {
            $memo->set($key, app(GramsConverter::class)->choices($recordId, $unitId));
        }

        return $memo->get($key);
    }

    /** What the usual rules give without a chosen size (null until record and unit are set). */
    private static function autoConversion(Get $get): ?GramsConversion
    {
        if (! $get('food_source_record_id') || ! $get('measure_unit_id')) {
            return null;
        }

        return app(GramsConverter::class)->resolve((int) $get('food_source_record_id'), (int) $get('measure_unit_id'), 1);
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
            if ($record instanceof FoodSourceRecord) {
                $key = $record->getAttribute('food_id').'|'.$record->getAttribute('food_form_id');
                $options[(int) $record->getKey()] = self::labelFor($record, $shared[$key] > 1);
            }
        }

        return $options;
    }

    /** "عدس — نيء", with its source when another record shares the food and form. */
    public static function labelFor(FoodSourceRecord $r, bool $withSource = false): string
    {
        $label = $r->foodForm ? "{$r->food?->name_ar} — {$r->foodForm->name_ar}" : (string) $r->food?->name_ar;
        if ($withSource) {
            $label .= " ({$r->dataSource?->name})";
        }
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
