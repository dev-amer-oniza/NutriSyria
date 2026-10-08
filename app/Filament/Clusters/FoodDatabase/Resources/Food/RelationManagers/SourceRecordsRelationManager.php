<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\RelationManagers;

use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use Filament\Resources\RelationManagers\RelationManager;

class SourceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'sourceRecords';

    protected static ?string $relatedResource = FoodSourceRecordResource::class;

    protected static ?string $title = 'مصادر الغذاء';
}
