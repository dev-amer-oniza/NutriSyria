<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Tables;

use App\Auth\Access;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('permissions')->withCount('users'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('label')
                    ->label('الدور')
                    ->state(fn (Role $record) => $record->displayName())
                    ->description(fn (Role $record) => $record->isBuiltIn() ? 'أساسي' : null)
                    ->weight('bold'),
                TextColumn::make('users_count')
                    ->label('عدد المستخدمين')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('permissions.name')
                    ->label('الصلاحيات')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Access::permissionLabels()[$state] ?? $state)
                    ->placeholder(fn ($record) => $record->name === Access::SUPER_ADMIN ? 'كل الصلاحيات' : 'لا شيء')
                    ->wrap(),
            ])
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
                self::deleteAction(DeleteAction::make()),
            ]);
    }

    /** A role still given to users is not deleted (the owner's decision 3a). */
    public static function deleteAction(DeleteAction $action): DeleteAction
    {
        return $action->before(function (DeleteAction $action, Role $record): void {
            $users = $record->users()->count();
            if ($users > 0) {
                Notification::make()
                    ->title('ما فيك تحذفه هلق')
                    ->body("هالدور عند {$users} مستخدم. غيّرلهم دورهم من صفحة المستخدمين أول.")
                    ->danger()->persistent()->send();
                $action->cancel();
            }
        });
    }
}
