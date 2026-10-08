<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ReadsSeedCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DataSourceSeeder extends Seeder
{
    use ReadsSeedCsv;

    public function run(): void
    {
        $now = now();

        // Insert-only: priorities, versions and citations edited in the dashboard are never overwritten.
        $rows = array_map(fn (array $r) => [
            'code' => $r['code'],
            'name' => $r['name'],
            'type' => $r['type'],
            'publisher' => $r['publisher'] ?: null,
            'version' => $r['version'] ?: null,
            'country' => $r['country'] ?: null,
            'url' => $r['url'] ?: null,
            'license' => $r['license'] ?: null,
            'citation' => $r['citation'] ?: null,
            'priority' => (int) $r['priority'],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->readCsv('data_sources.csv'));

        DB::table('data_sources')->insertOrIgnore($rows);
    }
}
