<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\RelationManagers;

use App\Models\FoodSourceRecord;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'مكونات الوصفة';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('measure_unit_id')
                    ->label('وحدة القياس')
                    ->relationship('measureUnit', 'name_ar')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('amount')
                    ->label('الكمية')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Toggle::make('is_added_after_cooking')
                    ->label('يُضاف بعد الطبخ')
                    ->helperText('مثال: زيت تزيين، ملح إضافي — لا يُحتسب في الوزن قبل الطبخ'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['ingredientRecord.food', 'ingredientRecord.foodForm', 'measureUnit']))
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
                TextColumn::make('is_added_after_cooking')
                    ->label('بعد الطبخ')
                    ->formatStateUsing(fn (bool $state) => $state ? 'نعم' : '—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                // لا CreateAction هنا عمداً: المكوّن يُختار من food_source_records الموجودة
                // أصلاً في القاعدة المركزية، لا يُنشأ من داخل وصفة.
                AssociateAction::make()
                    ->label('إضافة مكوّن')
                    ->recordSelect(fn ($select) => $select
                        ->getOptionLabelFromRecordUsing(fn (FoodSourceRecord $record) => sprintf(
                            '%s — %s (%s)',
                            $record->food?->name_ar,
                            $record->foodForm?->name_ar,
                            $record->dataSource?->name,
                        ))
                        ->relationship('food', 'name_ar')
                    )
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make()->label('إزالة من الوصفة'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ]);
    }
}
