<?php

namespace App\Filament\Clusters\AccessControl\Resources\Roles;

use App\Filament\Clusters\AccessControl\AccessControlCluster;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\CreateRole;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\EditRole;
use App\Filament\Clusters\AccessControl\Resources\Roles\Pages\ListRoles;
use App\Filament\Clusters\AccessControl\Resources\Roles\Schemas\RoleForm;
use App\Filament\Clusters\AccessControl\Resources\Roles\Tables\RolesTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel's roles (the owner's decisions 1a 3a): add one made of the existing permissions,
 * rename it, change its permissions, delete it when no user has it. The built-in roles keep
 * their code name and are never deleted (RolePolicy).
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ShieldCheck;

    protected static ?int $navigationSort = 2;

    protected static ?string $cluster = AccessControlCluster::class;

    protected static ?string $navigationLabel = 'الأدوار';

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Role ? $record->displayName() : '';
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
