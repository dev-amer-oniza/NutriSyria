<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users;

use App\Filament\Clusters\AccessControl\AccessControlCluster;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\AccessControl\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\AccessControl\Resources\Users\Schemas\UserForm;
use App\Filament\Clusters\AccessControl\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Users;

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = AccessControlCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    /** Sign-ups waiting for the brand owner or nutritionist role. */
    public static function getNavigationBadge(): ?string
    {
        $pending = User::whereNotNull('requested_role')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'طلبات أدوار تنتظر الموافقة';
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
