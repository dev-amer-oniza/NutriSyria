<?php

namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages;

use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\DataSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDataSource extends CreateRecord
{
    protected static string $resource = DataSourceResource::class;
}
