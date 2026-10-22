<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Schemas;

use App\Enums\Allergen;
use App\Models\FoodCategory;
use Filament\Forms\Components\CheckboxList;
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
                            ->arabicSearch()
                            ->searchable()
                            ->native(false),
                        TextInput::make('scientific_name')
                            ->label('الاسم العلمي')
                            ->helperText('اختياري — للنباتات والحيوانات فقط')
                            ->maxLength(255),
                    ]),
                Section::make('مسبّبات الحساسية')
                    ->description('تُقيَّم بها الوجبات لمن عنده حساسية: وجبة فيها مسبّب حساسيته تظهر له "غير مناسبة لك الآن".')
                    ->components([
                        CheckboxList::make('allergens')
                            ->label('يحتوي على')
                            ->options(collect(Allergen::cases())->mapWithKeys(fn (Allergen $a) => [$a->value => $a->label()])->all())
                            ->columns(4),
                        Toggle::make('allergens_checked')
                            ->label('تحققتُ من مسبّبات الحساسية لهذا الغذاء')
                            ->helperText('فعّله حتى لو لم تحدد شيئاً، ليُعرف أن الغذاء خالٍ منها. ما لم يُفعَّل، تُنبَّه من عنده حساسية بأن الوجبة لم تُفحص.'),
                    ]),
                Section::make('الصورة والحالة')
                    ->columns(2)
                    ->components([
                        FileUpload::make('img')
                            ->label('صورة الغذاء')
                            ->image()
                            ->disk('public')
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
