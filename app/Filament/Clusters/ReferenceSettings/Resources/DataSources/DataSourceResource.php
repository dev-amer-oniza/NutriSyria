<?php
namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources;

use App\Filament\Clusters\ReferenceSettings\ReferenceSettingsCluster;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages\CreateDataSource;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages\EditDataSource;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages\ListDataSources;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Schemas\DataSourceForm;
use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Tables\DataSourcesTable;
use App\Models\DataSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DataSourceResource extends Resource
{
    protected static ?string $model = DataSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BookOpen;

    protected static ?int $navigationSort = 1;

    protected static ?string $cluster = ReferenceSettingsCluster::class;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $navigationLabel      = 'مصادر البيانات';
    protected static ?string $modelLabel           = 'مصدر بيانات';
    protected static ?string $pluralModelLabel     = 'مصادر البيانات';

    public static function form(Schema $schema): Schema
    {
        return DataSourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DataSourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListDataSources::route('/'),
            'create' => CreateDataSource::route('/create'),
            'edit'   => EditDataSource::route('/{record}/edit'),
        ];
    }
}
