<?php

namespace App\Imports;

use App\Models\MeasureUnit;
use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Expects columns: USDA ID, English Name, Arabic Name, Notes
 * (matches MeasureUnitsExport::headings()).
 *
 * Matching key depends on whether the row has a USDA ID:
 *   - USDA ID present  -> upsert by usda_id (these are the canonical USDA units,
 *     re-importing the FDC measure unit list just refreshes names/notes).
 *   - USDA ID blank     -> this is a tenant/local-only unit (e.g. "رغيف", "ربطة").
 *     usda_id is nullable and NOT globally unique on NULL, so these are matched
 *     by name_en instead, to avoid creating duplicates on re-import.
 */
class MeasureUnitsImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function collection(SupportCollection $rows): void
    {
        foreach ($rows as $index => $row) {
            $usdaId = $row['usda_id'] ?? null;
            $nameEn = $row['english_name'] ?? null;

            if (blank($nameEn)) {
                $this->skipped++;
                $this->errors[] = "Row {$index}: missing English Name, skipped.";

                continue;
            }

            $matchKey = blank($usdaId)
                ? ['usda_id' => null, 'name_en' => $nameEn]
                : ['usda_id' => (int) $usdaId];

            MeasureUnit::updateOrCreate($matchKey, [
                'name_en' => $nameEn,
                'name_ar' => $row['arabic_name'] ?? null,
                'notes' => $row['notes'] ?? null,
            ]);

            $this->imported++;
        }
    }
}