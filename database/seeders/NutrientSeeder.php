<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ReadsSeedCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NutrientSeeder extends Seeder
{
    use ReadsSeedCsv;

    public function run(): void
    {
        $now = now();

        // Nutrients: insert-only. Once a nutritionist edits an Arabic name in the dashboard,
        // re-seeding must never overwrite it (and must never touch a unit that has values).
        $nutrients = array_map(fn (array $r) => [
            'code' => $r['code'],
            'infoods_tagname' => $r['infoods_tagname'] ?: null,
            'name_ar' => $r['name_ar'],
            'name_en' => $r['name_en'],
            'unit' => $r['unit'],
            'group' => $r['group'],
            'display_order' => (int) $r['display_order'],
            'is_core' => (bool) (int) $r['is_core'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->readCsv('nutrients.csv'));

        DB::table('nutrients')->insertOrIgnore($nutrients);

        // Source codes are technical configuration: the CSV stays the source of truth (upsert).
        $ids = DB::table('nutrients')->pluck('id', 'code');

        $codes = array_map(function (array $r) use ($ids, $now) {
            $nutrientId = $ids[$r['nutrient_code']]
                ?? throw new RuntimeException("Unknown nutrient_code '{$r['nutrient_code']}' in nutrient_source_codes.csv");

            return [
                'nutrient_id' => $nutrientId,
                'source_system' => $r['source_system'],
                'code' => $r['code'],
                'factor' => $r['factor'],
                'priority' => (int) $r['priority'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $this->readCsv('nutrient_source_codes.csv'));

        DB::table('nutrient_source_codes')->upsert(
            $codes,
            ['source_system', 'code'],
            ['nutrient_id', 'factor', 'priority', 'updated_at'],
        );
    }
}
