<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages;

use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImportBatch extends CreateRecord
{
    protected static string $resource = ImportBatchResource::class;
}
