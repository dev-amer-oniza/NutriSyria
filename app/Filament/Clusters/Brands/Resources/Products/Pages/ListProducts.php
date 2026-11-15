<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use HiddenFromAdmin;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
