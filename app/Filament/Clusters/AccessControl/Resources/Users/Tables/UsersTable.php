<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Tables;

use App\Auth\Access;
use App\Models\User;
use App\Services\Auth\RoleRequestService;
use App\Support\ArabicSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'brands']))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(query: ArabicSearch::column('name'))
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->label('الدور')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Access::roles()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Access::SUPER_ADMIN => 'danger',
                        Access::NUTRITIONIST => 'info',
                        default => 'primary',
                    })
                    ->placeholder('مستخدم الموقع'),
                TextColumn::make('requested_role')
                    ->label('طلب دور')
                    ->badge()
                    ->color('warning')
                    ->icon(Heroicon::OutlinedClock)
                    ->formatStateUsing(fn (string $state) => 'ينتظر: '.(Access::roles()[$state] ?? $state))
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('واتساب')
                    ->url(fn (User $record) => $record->phone ? 'https://wa.me/'.ltrim($record->phone, '+') : null, shouldOpenInNewTab: true)
                    ->extraAttributes(['dir' => 'ltr'])
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('brands.name')
                    ->label('البراندات')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('أُنشئ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options(Access::roles())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->role($data['value'])
                        : $query),
                TernaryFilter::make('is_active')->label('فعّال'),
                Filter::make('role_requests')
                    ->label('طلبات الأدوار المعلّقة')
                    ->query(fn (Builder $query) => $query->whereNotNull('requested_role')),
            ])
            ->recordActions([
                self::approveRoleAction(),
                self::declineRoleAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** Grant the role asked for at sign-up (brand owner or nutritionist). */
    private static function approveRoleAction(): Action
    {
        return Action::make('approveRole')
            ->label('اعتماد الطلب')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (User $record) => filled($record->requested_role))
            ->authorize(fn (User $record) => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'منح '.$record->name.' دور '.(Access::roles()[$record->requested_role] ?? ''))
            ->modalDescription('يستطيع بعدها الدخول إلى لوحة التحكم. لصاحب البراند: اربطه ببراندِه من صفحة تعديل المستخدم.')
            ->action(function (User $record) {
                app(RoleRequestService::class)->approve($record);
                Notification::make()->title('تم منح الدور.')->success()->send();
            });
    }

    private static function declineRoleAction(): Action
    {
        return Action::make('declineRole')
            ->label('رفض الطلب')
            ->icon(Heroicon::OutlinedXMark)
            ->color('gray')
            ->visible(fn (User $record) => filled($record->requested_role))
            ->authorize(fn (User $record) => auth()->user()?->can('update', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription('يبقى حسابه حساباً عادياً في الموقع، بلا لوحة تحكم.')
            ->action(function (User $record) {
                app(RoleRequestService::class)->decline($record);
                Notification::make()->title('تم رفض الطلب.')->send();
            });
    }
}
