<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Schemas;

use App\Auth\Access;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    /**
     * Permissions a role may be given from the panel. users.manage is left out on purpose: a
     * role that can manage accounts could make itself super-admin, so only the super-admin has it.
     *
     * @return list<string>
     */
    public static function grantable(): array
    {
        return array_values(array_diff(array_keys(Access::permissions()), [Access::USERS_MANAGE]));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('الصلاحيات')
                    ->description('ما يحق لهذا الدور فعله. حصر صاحب البراند ببراندات حسابه ثابت في النظام ولا يتغير من هنا.')
                    ->components([
                        CheckboxList::make('permissions')
                            ->hiddenLabel()
                            ->relationship(
                                'permissions',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereIn('name', self::grantable()),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => Access::permissions()[$record->name] ?? $record->name)
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
