<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Tables;

use App\Auth\Access;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                    ->searchable()
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
                    }),
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
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
