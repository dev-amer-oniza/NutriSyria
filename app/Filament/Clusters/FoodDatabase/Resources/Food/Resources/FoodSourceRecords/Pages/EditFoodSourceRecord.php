<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\Pages;

use App\Filament\Clusters\FoodDatabase\Resources\Food\Resources\FoodSourceRecords\FoodSourceRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFoodSourceRecord extends EditRecord
{
    protected static string $resource = FoodSourceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
