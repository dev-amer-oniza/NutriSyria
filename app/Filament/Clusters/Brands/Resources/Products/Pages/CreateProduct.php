<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Support\Concerns\HiddenFromAdmin;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use HiddenFromAdmin;

    protected static string $resource = ProductResource::class;
}
