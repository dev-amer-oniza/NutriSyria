<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ReadsSeedCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeasureUnitSeeder extends Seeder
{
    use ReadsSeedCsv;

    public function run(): void
    {
        $now = now();

        $units = array_map(fn (array $r) => [
            'code' => $r['code'],
            'name_ar' => $r['name_ar'],
            'name_en' => $r['name_en'] ?: null,
            'dimension' => $r['dimension'] ?: null,
            'base_factor' => $r['base_factor'] === '' ? null : $r['base_factor'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->readCsv('measure_units.csv'));

        DB::table('measure_units')->insertOrIgnore($units);

        $ids = DB::table('measure_units')->pluck('id', 'code');

        $aliases = array_map(function (array $r) use ($ids, $now) {
            $unitId = $ids[$r['unit_code']]
                ?? throw new RuntimeException("Unknown unit_code '{$r['unit_code']}' in measure_unit_aliases.csv");

            return [
                'measure_unit_id' => $unitId,
                'label' => mb_strtolower(trim($r['label'])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $this->readCsv('measure_unit_aliases.csv'));

        DB::table('measure_unit_aliases')->insertOrIgnore($aliases);
    }
}
