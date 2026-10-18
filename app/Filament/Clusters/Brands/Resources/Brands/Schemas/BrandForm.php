<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Schemas;

use App\Auth\Access;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات البراند')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('phone_1')
                            ->label('رقم الهاتف الأول')
                            ->tel()
                            ->maxLength(32),
                        TextInput::make('phone_2')
                            ->label('رقم الهاتف الثاني')
                            ->tel()
                            ->maxLength(32),
                        Toggle::make('has_delivery')
                            ->label('يقدّم توصيلاً')
                            ->default(false),
                        Toggle::make('is_active')
                            // Activating a brand is the admin's call, not the owner's.
                            ->disabled(fn () => ! auth()->user()?->allows(Access::BRANDS_MANAGE_ALL))
                            ->label('نشط')
                            ->default(true)
                            ->helperText('البراند غير النشط لا يظهر للمستخدمين'),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                    ]),
                Section::make('الشعار')
                    ->components([
                        FileUpload::make('logo')
                            ->label('شعار البراند')
                            ->image()
                            ->directory('brands')
                            ->imageEditor()
                            ->avatar(),
                    ]),
                // Who manages this brand: set by the admin only, never by the brand owner.
                Section::make('المسؤولون عن البراند')
                    ->description('أصحاب البراند الذين يرون هذا البراند ويعدّلونه.')
                    ->visible(fn () => auth()->user()?->allows(Access::BRANDS_MANAGE_ALL) ?? false)
                    ->components([
                        Select::make('managers')
                            ->hiddenLabel()
                            ->relationship('managers', 'name', modifyQueryUsing: fn ($query) => $query->role(Access::BRAND_OWNER))
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
