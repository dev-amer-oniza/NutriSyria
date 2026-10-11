<?php
namespace App\Filament\Clusters\Brands\Resources\Products\RelationManagers;

use App\Models\FoodSourceRecord;
use App\Services\Nutrition\GramsConversion;
use App\Services\Nutrition\GramsConverter;
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
                    ->required(),

                Select::make('measure_unit_id')
                    ->label('وحدة القياس')
                    ->relationship('measureUnit', 'name_ar')
                    ->required()
                    ->searchable()
                    ->live()
                    ->preload()
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
                            (int) $get('food_source_record_id'), (int) $get('measure_unit_id'), (float) ($get('amount') ?? 0)
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
                'ingredientRecord.food', 'ingredientRecord.foodForm', 'measureUnit',
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
        $key = implode('|', [$record->getKey(), $record->food_source_record_id, $record->measure_unit_id, $record->amount]);

        return $this->conversions[$key] ??= app(GramsConverter::class)->resolve(
            (int) $record->food_source_record_id, (int) $record->measure_unit_id, (float) $record->amount
        );
    }

    private static function labelFor(FoodSourceRecord $r): string
    {
        return sprintf('%s — %s (%s)', $r->food?->name_ar, $r->foodForm?->name_ar, $r->dataSource?->name);
    }

}
