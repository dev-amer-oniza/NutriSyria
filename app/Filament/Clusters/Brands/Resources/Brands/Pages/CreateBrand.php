<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Models\Brand;
use App\Services\Brands\BrandLinks;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function afterCreate(): void
    {
        if ($this->record instanceof Brand) {
            app(BrandLinks::class)->brandSaved($this->record, []);
        }
    }
}
