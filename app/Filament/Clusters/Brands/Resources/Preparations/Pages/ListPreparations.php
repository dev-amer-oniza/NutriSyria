<?php

namespace App\Filament\Clusters\Brands\Resources\Preparations\Pages;

use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPreparations extends ListRecords
{
    use HiddenFromAdmin;

    protected static string $resource = PreparationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة تحضير')];
    }
}
