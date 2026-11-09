<?php

namespace App\Filament\Clusters\Kitchen\Resources\Dishes\Pages;

use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;
use App\Models\Brand;
use Filament\Resources\Pages\CreateRecord;

class CreateKitchenDish extends CreateRecord
{
    protected static string $resource = KitchenDishResource::class;

    /** Every dish here is the platform kitchen's, whatever the form sent. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ['brand_id' => Brand::platform()->id] + $data;
    }

    /** Next: its ingredients, on the dish's page. */
    protected function getRedirectUrl(): string
    {
        return KitchenDishResource::getUrl('edit', ['record' => $this->record]);
    }
}
