<?php

namespace App\Filament\Clusters\Consultations\Resources\ConsultationRequests\Pages;

use App\Filament\Clusters\Consultations\Resources\ConsultationRequests\ConsultationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListConsultationRequests extends ListRecords
{
    protected static string $resource = ConsultationRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'قبل القبول ترى الاسم والسبب والعمر والجنس والهدف فقط. بعده يظهر رقم واتساب، والسجل الصحي في "مراجعيني" لاحقاً. الطلب بلا رد ينتهي بعد 7 أيام.';
    }
}
