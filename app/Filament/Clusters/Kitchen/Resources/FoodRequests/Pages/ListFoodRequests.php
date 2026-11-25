<?php

namespace App\Filament\Clusters\Kitchen\Resources\FoodRequests\Pages;

use App\Filament\Clusters\Kitchen\Resources\FoodRequests\FoodRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListFoodRequests extends ListRecords
{
    protected static string $resource = FoodRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'أكلات أكلها الناس وما لقوها بالموقع. أضف الأكلة (للمطبخ السوري أو لمطعم) واعتمدها، ثم اربط الطلب بها: يوصل إشعار لكل من طلبها. الطلبات لنفس الأكلة تُجاب معاً.';
    }
}
