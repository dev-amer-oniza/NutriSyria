<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Tables;

use App\Auth\Access;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('permissions')->withCount('users'))
            ->columns([
                TextColumn::make('name')
                    ->label('الدور')
                    ->formatStateUsing(fn (string $state) => Access::roles()[$state] ?? $state)
                    ->weight('bold'),
                TextColumn::make('users_count')
                    ->label('عدد المستخدمين')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('permissions.name')
                    ->label('الصلاحيات')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Access::permissions()[$state] ?? $state)
                    ->placeholder(fn ($record) => $record->name === Access::SUPER_ADMIN ? 'كل الصلاحيات' : 'لا شيء')
                    ->wrap(),
            ])
            ->paginated(false)
            ->recordActions([
                EditAction::make()->label('تعديل الصلاحيات'),
            ]);
    }
}
