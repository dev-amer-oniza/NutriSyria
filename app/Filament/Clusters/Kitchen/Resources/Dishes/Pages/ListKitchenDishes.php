<?php

namespace App\Filament\Clusters\Kitchen\Resources\Dishes\Pages;

use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKitchenDishes extends ListRecords
{
    protected static string $resource = KitchenDishResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة وجبة')];
    }
}
