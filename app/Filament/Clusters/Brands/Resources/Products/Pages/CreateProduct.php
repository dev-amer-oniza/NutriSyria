<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
