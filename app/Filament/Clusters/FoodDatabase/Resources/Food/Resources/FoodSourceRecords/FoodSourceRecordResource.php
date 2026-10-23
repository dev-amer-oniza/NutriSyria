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
use Filament\Resources\ParentResourceRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FoodSourceRecordResource extends Resource
{
    protected static ?string $model = FoodSourceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::DocumentText;

    /**
     * Nested under a food. Filament guesses the relationship from the model name
     * (foodSourceRecords), but Food calls it sourceRecords: named here, or creating a
     * record from a food's page fails.
     */
    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return FoodResource::asParent(childResource: static::class)
            ->relationship('sourceRecords')
            ->inverseRelationship('food');
    }

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
