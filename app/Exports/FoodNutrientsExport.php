<?php

namespace App\Exports;

use App\Models\FoodNutrient;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FoodNutrientsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return FoodNutrient::query()->with('food')->orderBy('food_id');
    }

    public function headings(): array
    {
        return [
            'FDC ID',
            'Arabic Name',
            'Nutrient ID',
            'Nutrient Name',
            'Unit',
            'Amount',
        ];
    }

    /**
     * @param  FoodNutrient  $foodNutrient
     */
    public function map($foodNutrient): array
    {
        return [
            $foodNutrient->food?->fdc_id,
            $foodNutrient->food?->name_ar,
            $foodNutrient->nutrient_id,
            $foodNutrient->nutrient_name,
            $foodNutrient->unit,
            $foodNutrient->amount,
        ];
    }
}
