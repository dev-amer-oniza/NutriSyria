<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords;

use App\Filament\Clusters\FoodDatabase\Resources\Food\FoodResource;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Pages\CreateFoodSourceRecord;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Pages\EditFoodSourceRecord;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\RelationManagers\PortionsRelationManager;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Schemas\FoodSourceRecordForm;
use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Tables\FoodSourceRecordsTable;
use App\Models\FoodSourceRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FoodSourceRecordResource extends Resource
{
    protected static ?string $model = FoodSourceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = FoodResource::class;

    protected static ?string $modelLabel = 'مصدر';

    protected static ?string $pluralModelLabel = 'مصادر الغذاء';

    public static function form(Schema $schema): Schema
    {
        return FoodSourceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FoodSourceRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PortionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateFoodSourceRecord::route('/create'),
            'edit' => EditFoodSourceRecord::route('/{record}/edit'),
        ];
    }
}
