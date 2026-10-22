<?php

namespace App\Filament\Clusters\Consultations\Resources\Clients\Pages;

use App\Filament\Clusters\Consultations\Resources\Clients\ClientResource;
use Filament\Resources\Pages\ListRecords;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    public function getSubheading(): ?string
    {
        return 'افتح ملف المراجع لترى سجله الصحي، وتختار قالبه وتحدد معاييره.';
    }
}
