<?php
namespace App\Filament\Clusters\FoodDatabase\Resources\Food;

use App\Filament\Clusters\FoodDatabase\FoodDatabaseCluster;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\CreateFood;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\EditFood;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Pages\ListFood;
use App\Filament\Clusters\FoodDatabase\Resources\Food\RelationManagers\SourceRecordsRelationManager;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Schemas\FoodForm;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Tables\FoodTable;
use App\Models\Food;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FoodResource extends Resource
{
    protected static ?string $model = Food::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = FoodDatabaseCluster::class;

    protected static ?string $recordTitleAttribute = 'name_ar';
    protected static ?string $navigationLabel      = 'الأغذية';

    protected static ?string $modelLabel = 'غذاء';

    protected static ?string $pluralModelLabel = 'الأغذية';

    public static function form(Schema $schema): Schema
    {
        return FoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoodTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SourceRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFood::route('/'),
            'create' => CreateFood::route('/create'),
            'edit'   => EditFood::route('/{record}/edit'),
        ];
    }
}
