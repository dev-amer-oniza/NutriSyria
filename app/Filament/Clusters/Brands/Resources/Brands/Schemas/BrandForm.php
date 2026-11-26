<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Schemas;

use App\Auth\Access;
use App\Enums\BrandKind;
use App\Enums\SyrianGovernorate;
use App\Models\Brand;
use App\Services\Brands\ClientOwner;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('بيانات العميل')
                    ->columns(2)
                    ->components([
                        // "إضافة عميل" starts from a signed-up account (the owner's decision): the
                        // pages hand it to ClientOwner, which also gives a plain account its role.
                        Select::make('owner_id')
                            ->label('صاحب الحساب')
                            ->helperText(fn (?Brand $record) => $record
                                ? 'المسؤول عن هذا العميل. إذا غيّرته، بينتقل له وبينفك الربط عن القديم.'
                                : 'اختار إيميل شخص مسجّل بالموقع. بيصير هو المسؤول، وبتنفتحله لوحة التحكم.')
                            ->options(fn () => app(ClientOwner::class)->options())
                            ->searchable()
                            // A new client needs one; an older one without an owner can be saved as it is.
                            ->required(fn (?Brand $record) => $record === null)
                            ->dehydrated(false)
                            ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpanFull(),
                        // Restaurant or brand: the admin's call (decision 1a), shown to the owner.
                        ToggleButtons::make('kind')
                            ->label('النوع')
                            ->options(BrandKind::class)
                            ->default(BrandKind::Restaurant->value)
                            ->inline()
                            ->required()
                            ->disabled(fn () => ! auth()->user()?->isSuperAdmin())
                            ->helperText(fn () => auth()->user()?->isSuperAdmin()
                                ? 'يظهر للناس بالموقع: «مطعم» أو «براند».'
                                : 'تحدّده إدارة NutriSyria. ويظهر للناس بالموقع.')
                            ->columnSpanFull(),
                        TextInput::make('name')
                            ->label('اسم المطعم أو البراند')
                            ->helperText('هذا ما يراه الناس بالموقع.')
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
                            // Activating a brand is the admin's call: the owner does not see it at all
                            // (the owner's request). A hidden field is not sent, so it cannot change.
                            ->visible(fn () => (bool) auth()->user()?->allows(Access::BRANDS_MANAGE_ALL))
                            ->label('نشط')
                            ->default(true)
                            ->helperText('غير النشط لا يظهر للناس بالموقع'),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                    ]),
                // Shown on the restaurant's page on the site; empty fields are not shown.
                Section::make('التواصل والموقع')
                    ->description('تظهر في صفحة المطعم على الموقع. اترك الحقل فارغاً إن لم ينطبق.')
                    ->columns(2)
                    ->components([
                        Select::make('city')
                            ->label('المحافظة')
                            ->options(SyrianGovernorate::options()),
                        TextInput::make('address')
                            ->label('العنوان')
                            ->placeholder('دمشق، المزة، شارع الجلاء')
                            ->maxLength(200),
                        TextInput::make('opening_hours')
                            ->label('ساعات الدوام')
                            ->placeholder('يومياً من 10 صباحاً حتى 12 ليلاً')
                            ->maxLength(200),
                        TextInput::make('whatsapp')
                            ->label('واتساب')
                            ->tel()
                            ->placeholder('0912 345 678')
                            ->maxLength(32),
                        TextInput::make('instagram_url')
                            ->label('رابط إنستغرام')
                            ->url()
                            ->rules(['url:http,https'])
                            ->maxLength(255),
                        TextInput::make('facebook_url')
                            ->label('رابط فيسبوك')
                            ->url()
                            ->rules(['url:http,https'])
                            ->maxLength(255),
                        TextInput::make('delivery_note')
                            ->label('ملاحظة التوصيل')
                            ->placeholder('توصيل لكل دمشق، أو عبر تطبيق طلبات')
                            ->helperText('تظهر فقط إن كان يقدّم توصيلاً.')
                            ->maxLength(200)
                            ->columnSpanFull(),
                    ]),
                Section::make('الشعار')
                    ->components([
                        FileUpload::make('logo')
                            ->label('الشعار')
                            ->image()
                            ->disk('public')
                            ->directory('brands')
                            ->imageEditor()
                            ->avatar(),
                    ]),
            ]);
    }
}
