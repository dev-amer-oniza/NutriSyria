<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Schemas;

use App\Enums\RecipeStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductRecipeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات الوصفة')
                    ->columns(2)
                    ->components([
                        TextInput::make('servings')
                            ->label('عدد الحصص')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Select::make('status')
                            ->label('الحالة')
                            ->options(RecipeStatus::class)
                            ->default(RecipeStatus::Draft)
                            ->required(),
                        TextInput::make('weight_before_cooking_g')
                            ->label('الوزن قبل الطبخ')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('غرام'),
                        TextInput::make('weight_after_cooking_g')
                            ->label('الوزن بعد الطبخ')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('غرام')
                            ->helperText('يُستخدم لحساب فقدان الوزن أثناء الطبخ عند التحليل'),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
