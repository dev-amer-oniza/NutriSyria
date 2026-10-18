<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Schemas;

use App\Auth\Access;
use App\Auth\AccountRules;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('الحساب')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->rule(Password::min(8)->letters()->numbers())
                            ->required(fn (string $operation) => $operation === 'create')
                            // Left empty on edit: the current password stays.
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit'
                                ? 'اتركها فارغة للإبقاء على كلمة المرور الحالية. 8 أحرف على الأقل، فيها حروف وأرقام.'
                                : '8 أحرف على الأقل، فيها حروف وأرقام.'),
                        Toggle::make('is_active')
                            ->label('الحساب فعّال')
                            ->default(true)
                            ->helperText('الحساب الموقوف يخرج من اللوحة فوراً ولا يستطيع الدخول.')
                            ->rule(fn (?User $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                                if ($reason = AccountRules::deactivation($record, (bool) $value, auth()->user())) {
                                    $fail($reason);
                                }
                            }),
                    ]),
                Section::make('الدور والبراندات')
                    ->columns(2)
                    ->components([
                        Select::make('role')
                            ->label('الدور')
                            ->options(Access::roles())
                            ->in(array_keys(Access::roles()))
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(fn (Get $get) => match ($get('role')) {
                                Access::SUPER_ADMIN => 'كل الصلاحيات، ومنها إدارة المستخدمين.',
                                Access::BRAND_OWNER => 'يرى ويعدّل البراندات المختارة هنا فقط، ومنتجاتها ووصفاتها.',
                                Access::NUTRITIONIST => 'يراجع دفعات الاستيراد ويعتمد القيم الغذائية الرسمية.',
                                default => null,
                            })
                            ->rule(fn (?User $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                                if ($reason = AccountRules::roleChange($record, $value, auth()->user())) {
                                    $fail($reason);
                                }
                            }),
                        Select::make('brands')
                            ->label('البراندات التي يديرها')
                            ->relationship('brands', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->visible(fn (Get $get) => $get('role') === Access::BRAND_OWNER)
                            ->required(fn (Get $get) => $get('role') === Access::BRAND_OWNER)
                            ->helperText('صاحب البراند لا يرى أي براند غير المختار هنا.'),
                    ]),
            ]);
    }
}
