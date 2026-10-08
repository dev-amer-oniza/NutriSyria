<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\ReadsSeedCsv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoodFormSeeder extends Seeder
{
    use ReadsSeedCsv;

    public function run(): void
    {
        $now = now();

        // Insert-only: forms your nutritionist edits or adds in the dashboard are never overwritten.
        $rows = array_map(fn (array $r) => [
            'code' => $r['code'],
            'name_ar' => $r['name_ar'],
            'name_en' => $r['name_en'] ?: null,
            'group' => $r['group'] ?: null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->readCsv('food_forms.csv'));

        DB::table('food_forms')->insertOrIgnore($rows);
    }
}
