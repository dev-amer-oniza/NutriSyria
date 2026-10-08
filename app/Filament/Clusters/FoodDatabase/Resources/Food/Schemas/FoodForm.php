<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Schemas;

use App\Models\FoodCategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('البيانات الأساسية')
                    ->columns(2)
                    ->components([
                        TextInput::make('name_ar')
                            ->label('الاسم بالعربية')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('name_en')
                            ->label('الاسم بالإنجليزية')
                            ->maxLength(255),
                        Select::make('food_category_id')
                            ->label('التصنيف')
                            ->relationship('category', 'name_ar')
                            ->searchable()
                            ->native(false),
                        TextInput::make('scientific_name')
                            ->label('الاسم العلمي')
                            ->helperText('اختياري — للنباتات والحيوانات فقط')
                            ->maxLength(255),
                    ]),
                Section::make('الصورة والحالة')
                    ->columns(2)
                    ->components([
                        FileUpload::make('img')
                            ->label('صورة الغذاء')
                            ->image()
                            ->directory('foods')
                            ->imageEditor(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->helperText('الأغذية غير النشطة لا تظهر للمستخدمين'),
                    ]),
            ]);
    }
}
