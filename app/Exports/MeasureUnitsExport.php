<?php

namespace App\Exports;

use App\Models\MeasureUnit;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MeasureUnitsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return MeasureUnit::query()->orderBy('name_en');
    }

    public function headings(): array
    {
        return [
            'USDA ID',
            'English Name',
            'Arabic Name',
            'Notes',
        ];
    }

    /**
     * @param  MeasureUnit  $measureUnit
     */
    public function map($measureUnit): array
    {
        return [
            $measureUnit->usda_id,
            $measureUnit->name_en,
            $measureUnit->name_ar,
            $measureUnit->notes,
        ];
    }
}