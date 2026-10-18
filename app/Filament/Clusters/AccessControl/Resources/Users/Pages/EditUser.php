<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Pages;

use App\Filament\Clusters\AccessControl\Resources\Users\Pages\Concerns\SavesRole;
use App\Filament\Clusters\AccessControl\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SavesRole;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->takeRole($data);
    }

    protected function afterSave(): void
    {
        $this->applyRole($this->record);
    }
}
