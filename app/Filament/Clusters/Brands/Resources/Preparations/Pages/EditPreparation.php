<?php

namespace App\Filament\Clusters\Brands\Resources\Preparations\Pages;

use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPreparation extends EditRecord
{
    use HiddenFromAdmin;

    protected static string $resource = PreparationResource::class;

    protected function getHeaderActions(): array
    {
        return [PreparationResource::deleteAction(DeleteAction::make())];
    }
}
