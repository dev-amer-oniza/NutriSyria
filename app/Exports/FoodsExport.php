<?php

namespace App\Exports;

use App\Models\Food;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FoodsExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Food::query()->orderBy('name_ar');
    }

    public function headings(): array
    {
        return [
            'FDC ID',
            'Arabic Name',
            'English Name',
            'Description',
            'Data Type',
            'Category',
        ];
    }

    /**
     * @param  Food  $food
     */
    public function map($food): array
    {
        return [
            $food->fdc_id,
            $food->name_ar,
            $food->name_en,
            $food->description,
            $food->data_type,
            $food->category,
        ];
    }
}
