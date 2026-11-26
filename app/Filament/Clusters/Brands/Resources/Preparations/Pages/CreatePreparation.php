<?php

namespace App\Filament\Clusters\Brands\Resources\Preparations\Pages;

use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use Filament\Resources\Pages\CreateRecord;

class CreatePreparation extends CreateRecord
{
    use HiddenFromAdmin;

    protected static string $resource = PreparationResource::class;

    /** A single-brand owner never picks the brand: it is theirs. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['brand_id'] ??= PreparationResource::onlyBrandId();

        return $data;
    }
}
