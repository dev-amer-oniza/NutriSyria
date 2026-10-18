<?php

namespace App\Filament\Clusters\ImportReview\Resources\ImportBatches\Pages;

use App\Filament\Clusters\ImportReview\Resources\ImportBatches\ImportBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * The batch review page: batch details on top, its records below with approve / reject. The
 * batch itself has nothing to edit, so there is no save button.
 */
class EditImportBatch extends EditRecord
{
    protected static string $resource = ImportBatchResource::class;

    public function getTitle(): string
    {
        return 'مراجعة الدفعة: '.$this->record->original_filename;
    }

    public function getBreadcrumb(): string
    {
        return 'مراجعة';
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
