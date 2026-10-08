<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                Section::make('الصورة')
                    ->components([
                        FileUpload::make('img')
                            ->label('صورة المنتج')
                            ->image()
                            ->directory('products')
                            ->imageEditor(),
                    ]),
            ]);
    }
}
