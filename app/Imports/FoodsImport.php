<?php

namespace App\Imports;

use App\Models\Food;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Expects columns: FDC ID, Arabic Name, English Name, Description, Data Type, Category
 * (matches FoodsExport::headings(), so a round-tripped export/import works as-is).
 *
 * Rows are upserted by fdc_id, so re-importing the same file updates existing
 * foods instead of creating duplicates.
 */
class FoodsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(SupportCollection $rows): void
    {
       
        foreach ($rows as $index => $row) {
            $fdcId = $row['fdc_id'] ?? null;

            if (blank($fdcId)) {
                $this->skipped++;
                $this->errors[] = "Row {$index}: missing FDC ID, skipped.";

                continue;
            }

            Food::create([
                'fdc_id' => (int) $fdcId,
                'name_ar' => $row['arabic_name'] ?? null,
                'name_en' => $row['english_query'] ?? null,
                'description' => $row['description'] ?? null,
                'data_type' => $row['data_type'] ?? null,
                'category' => $row['category'] ?? null,
            ]);

            $this->imported++;
        }
    }
}
