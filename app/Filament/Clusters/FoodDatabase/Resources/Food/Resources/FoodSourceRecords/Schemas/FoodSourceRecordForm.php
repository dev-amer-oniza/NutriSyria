<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Schemas;

use App\Enums\ConfidenceLevel;
use App\Enums\FoodSourceStatus;
use App\Enums\NutrientValueMethod;
use App\Enums\ValueQualifier;
use App\Models\Nutrient;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FoodSourceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات المصدر')
                    ->columns(2)
                    ->components([
                        Select::make('food_form_id')
                            ->label('الشكل')
                            ->relationship('foodForm', 'name_ar')
                            ->required()
                            ->preload()
                            ->searchable()
                            ->native(false),
                        Select::make('data_source_id')
                            ->label('المصدر')
                            ->relationship('dataSource', 'name')
                            ->required()
                            ->searchable()
                            ->native(false),
                        TextInput::make('external_ref')
                            ->label('الرقم المرجعي')
                            ->helperText('مثال: رقم العنصر في USDA')
                            ->maxLength(128),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(FoodSourceStatus::class)
                            ->default(FoodSourceStatus::PendingReview)
                            ->required(),
                        Toggle::make('is_preferred')
                            ->label('مصدر مفضّل لهذا الغذاء'),
                        TextInput::make('refuse_pct')
                            ->label('نسبة الفاقد (غير الصالح للأكل)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100),
                    ]),

                Section::make('القيم الغذائية')
                    ->description('القيم لكل 100 غرام من هذا الشكل')
                    ->components([
                        Repeater::make('nutrientValues')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(3)
                                    ->components([
                                        Select::make('nutrient_id')
                                            ->label('المغذي')
                                            ->relationship('nutrient', 'name_ar')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->distinct()
                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                            ->columnSpan(2),
                                        TextInput::make('amount_per_100g')
                                            ->label('القيمة')
                                            ->numeric()
                                            ->minValue(0)
                                            ->suffix(fn (Get $get) => Nutrient::find($get('nutrient_id'))?->unit)
                                            ->columnSpan(1),
                                    ]),
                                Section::make('تفاصيل إضافية')
                                    ->collapsed()
                                    ->columns(3)
                                    ->components([
                                        Select::make('value_qualifier')
                                            ->label('ملاحظة القيمة')
                                            ->options(ValueQualifier::class)
                                            ->placeholder('لا شيء')
                                            ->native(false),
                                        Select::make('method')
                                            ->label('طريقة الحصول عليها')
                                            ->options(NutrientValueMethod::class)
                                            ->default(NutrientValueMethod::Imported)
                                            ->native(false),
                                        Select::make('confidence_level')
                                            ->label('درجة الثقة')
                                            ->options(ConfidenceLevel::class)
                                            ->default(ConfidenceLevel::Reference)
                                            ->native(false),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state) => Nutrient::find($state['nutrient_id'] ?? null)?->name_ar)
                            ->addActionLabel('إضافة مغذٍّ لم يكن ضمن القائمة')
                            ->default(fn () => Nutrient::query()
                                ->where('is_active', true)
                                ->orderBy('display_order')
                                ->get(['id'])
                                ->map(fn (Nutrient $n) => ['nutrient_id' => $n->id])
                                ->toArray())
                            ->collapsible()
                            ->reorderable(false),
                    ]),

                Section::make('الصلاحية والملاحظات')
                    ->columns(2)
                    ->collapsed()
                    ->components([
                        DatePicker::make('valid_from')->label('صالح من'),
                        DatePicker::make('valid_to')->label('صالح حتى'),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
