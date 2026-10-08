<?php

namespace App\Exports;

use App\Models\FoodPortion;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FoodPortionsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return FoodPortion::query()
            ->with(['food', 'measureUnit'])
            ->orderBy('food_id');
    }

    public function headings(): array
    {
        return [
            'FDC ID',
            'Arabic Name',
            'Measure Unit USDA ID',
            'Measure Unit English Name',
            'Amount',
            'Modifier',
            'Gram Weight',
            'Data Points',
        ];
    }

    /**
     * @param  FoodPortion  $foodPortion
     */
    public function map($foodPortion): array
    {
        return [
            $foodPortion->food?->fdc_id,
            $foodPortion->food?->name_ar,
            $foodPortion->measureUnit?->usda_id,
            $foodPortion->measureUnit?->name_en,
            $foodPortion->amount,
            $foodPortion->modifier,
            $foodPortion->gram_weight,
            $foodPortion->data_points,
        ];
    }
}