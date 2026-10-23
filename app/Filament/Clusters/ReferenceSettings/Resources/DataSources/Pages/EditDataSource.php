<?php

namespace App\Filament\Clusters\ReferenceSettings\Resources\DataSources\Pages;

use App\Filament\Clusters\ReferenceSettings\Resources\DataSources\DataSourceResource;
use App\Models\DataSource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDataSource extends EditRecord
{
    protected static string $resource = DataSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, DataSource $record) {
                    if ($reason = $record->deletionBlocker()) {
                        Notification::make()->title($reason)->danger()->persistent()->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
