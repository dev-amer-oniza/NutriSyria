<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles\Schemas;

use App\Auth\Access;
use App\Models\Role;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
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
                Section::make('الدور')
                    ->components([
                        TextInput::make('label')
                            ->label('اسم الدور')
                            ->placeholder('مثلاً: مدخل بيانات')
                            ->required()
                            ->maxLength(80)
                            ->unique('roles', 'label', ignoreRecord: true)
                            ->validationMessages(['unique' => 'في دور بهذا الاسم.'])
                            ->helperText(fn (?Role $record) => $record?->isBuiltIn()
                                ? 'دور أساسي بالنظام: فيك تغيّر اسمه وصلاحياته، بس ما بينحذف.'
                                : null),
                    ]),
                Section::make('الصلاحيات')
                    ->description('شو بيقدر يعمل هالدور. حصر صاحب البراند ببراندات حسابه ثابت بالنظام وما بيتغيّر من هون.')
                    ->components([
                        CheckboxList::make('permissions')
                            ->hiddenLabel()
                            ->relationship(
                                'permissions',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereIn('name', self::grantable()),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => Access::permissionLabels()[$record->name] ?? $record->name)
                            ->descriptions(fn () => collect(self::grantable())
                                ->mapWithKeys(fn (string $name) => [$name => Access::permissionDescription($name)])
                                ->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
