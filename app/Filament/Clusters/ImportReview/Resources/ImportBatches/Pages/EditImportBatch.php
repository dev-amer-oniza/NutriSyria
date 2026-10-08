<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages;

use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportBatch extends EditRecord
{
    protected static string $resource = ImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
