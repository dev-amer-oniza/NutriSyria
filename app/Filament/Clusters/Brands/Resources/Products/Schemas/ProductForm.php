<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Schemas;

use App\Enums\RecipeStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات المنتج')
                    ->columns(2)
                    ->components([
                        Select::make('brand_id')
                            ->label('البراند')
                            ->relationship('brand', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->helperText('المنتج غير النشط لا يظهر للمستخدمين'),
                        Textarea::make('description')
                            ->label('الوصف (نص تسويقي للقائمة)')
                            ->columnSpanFull(),
                    ]),
                Section::make('الوصفة')
                    ->columns(2)
                    ->relationship('recipe')
                    ->components([
                        TextInput::make('servings')
                            ->label('عدد الحصص')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Select::make('status')
                            ->label('حالة الوصفة')
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
                    ]),
                Section::make('الصورة')
                    ->components([
                        FileUpload::make('img')
                            ->label('صورة المنتج')
                            ->image()
                            ->directory('products')
                            ->imageEditor(),
                    ]),
                Section::make('التحليل الغذائي الرسمي')
                    ->visible(fn ($record) => $record && $record->nutrientValues()->exists())
                    ->components([
                        View::make('filament.clusters.brands.components.product-analysis-summary')
                            ->viewData(fn ($record) => ['product' => $record]),
                    ]),
            ]);
    }
}
