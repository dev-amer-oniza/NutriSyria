<?php

namespace App\Filament\Clusters\AccessControl\Resources\Users\Pages;

use App\Filament\Clusters\AccessControl\Resources\Users\Pages\Concerns\SavesRole;
use App\Filament\Clusters\AccessControl\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SavesRole;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->takeRole($data);
    }

    protected function afterCreate(): void
    {
        $this->applyRole($this->record);
    }
}
