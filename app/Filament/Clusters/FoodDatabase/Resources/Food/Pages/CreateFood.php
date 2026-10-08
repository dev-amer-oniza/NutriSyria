<?php

namespace App\Filament\Clusters\FoodDatabase\Resources\Food\Pages;

use App\Filament\Clusters\FoodDatabase\Resources\Food\FoodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFood extends CreateRecord
{
    protected static string $resource = FoodResource::class;
}
