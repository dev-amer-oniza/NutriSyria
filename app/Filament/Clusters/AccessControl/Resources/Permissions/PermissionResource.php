<?php

namespace App\Filament\Clusters\AccessControl\Resources\Permissions;

use App\Auth\Access;
use App\Filament\Clusters\AccessControl\AccessControlCluster;
use App\Filament\Clusters\AccessControl\Resources\Permissions\Pages\ManagePermissions;
use App\Models\Permission;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "الصلاحيات": every lock the code checks, with what it opens and which roles have it (the
 * owner's decision 2a). Its Arabic name and description can be edited; none is added or
 * deleted here, since a new one would open nothing and a deleted one would break its page.
 */
class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Key;

    protected static ?int $navigationSort = 3;

    protected static ?string $cluster = AccessControlCluster::class;

    protected static ?string $navigationLabel = 'الصلاحيات';

    protected static ?string $modelLabel = 'صلاحية';

    protected static ?string $pluralModelLabel = 'الصلاحيات';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('name', array_keys(Access::permissions()));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('label')
                ->label('الاسم')
                ->required()
                ->maxLength(120),
            Textarea::make('description')
                ->label('شو بتفتح؟')
                ->rows(3)
                ->maxLength(300),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->defaultSort('id')
            ->description('كل صلاحية قفل بالكود. فيك تغيّر اسمها وشرحها، وتعطيها لدور من صفحة «الأدوار».')
            ->columns([
                TextColumn::make('label')
                    ->label('الصلاحية')
                    ->state(fn (Permission $record) => Access::permissionLabels()[$record->name] ?? $record->name)
                    ->description(fn (Permission $record) => Access::permissionDescription($record->name))
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('roles.name')
                    ->label('الأدوار اللي معها')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Access::roles()[$state] ?? $state)
                    ->placeholder(fn (Permission $record) => $record->name === Access::USERS_MANAGE ? 'المدير وحده' : 'ولا دور')
                    ->wrap(),
                TextColumn::make('name')
                    ->label('الاسم بالكود')
                    ->fontFamily('mono')
                    ->size('xs')
                    ->color('gray'),
            ])
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePermissions::route('/'),
        ];
    }
}
