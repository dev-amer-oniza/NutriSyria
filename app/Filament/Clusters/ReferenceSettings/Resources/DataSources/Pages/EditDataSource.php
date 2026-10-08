<?php

namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages;

use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\DataSourceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDataSource extends EditRecord
{
    protected static string $resource = DataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
