<?php

namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DataSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات المصدر')
                    ->columns(2)
                    ->components([
                        TextInput::make('code')
                            ->label('الرمز')
                            ->helperText('مثال: usda_fdc_sr_legacy — يُستخدم في ملفات الاستيراد ولا يمكن تغييره لاحقاً بسهولة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(64),
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('النوع')
                            ->options([
                                'usda_fdc' => 'USDA FoodData Central',
                                'national_table' => 'جدول تركيب غذائي وطني',
                                'lab' => 'تحليل مخبري',
                                'recipe' => 'وصفات محسوبة',
                                'manual' => 'إدخال يدوي',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('publisher')
                            ->label('الجهة الناشرة')
                            ->maxLength(255),
                        TextInput::make('version')
                            ->label('رقم الإصدار')
                            ->helperText('مثال: 2018-04')
                            ->maxLength(64),
                        DatePicker::make('published_at')
                            ->label('تاريخ النشر'),
                        TextInput::make('country')
                            ->label('البلد')
                            ->maxLength(12),
                        TextInput::make('priority')
                            ->label('الأولوية')
                            ->helperText('الرقم الأصغر يفوز عند تعارض عدة مصادر لنفس الغذاء')
                            ->numeric()
                            ->default(100)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true),
                    ]),
                Section::make('التوثيق')
                    ->collapsed()
                    ->components([
                        TextInput::make('url')
                            ->label('الرابط')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('license')
                            ->label('الترخيص')
                            ->helperText('مثال: CC0 1.0')
                            ->maxLength(255),
                        Textarea::make('citation')
                            ->label('طريقة الاستشهاد بالمصدر')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
