<?php

namespace App\Console\Commands;

use App\Services\Site\Kitchen\KitchenRecipeFile;
use App\Services\Site\Kitchen\KitchenRecipeImporter;
use Illuminate\Console\Command;

/**
 * php artisan kitchen:import breakfast — adds the dishes of database/data/kitchen/breakfast.csv
 * to "المطبخ السوري". Run it again after adding dishes to the file: only the new ones are added.
 */
class KitchenImport extends Command
{
    protected $signature = 'kitchen:import {section : the file name in database/data/kitchen, without .csv}';

    protected $description = 'Add the dishes of a kitchen recipe file to the Syrian kitchen (not approved)';

    public function handle(KitchenRecipeImporter $importer): int
    {
        $section = (string) $this->argument('section');
        $path = database_path("data/kitchen/{$section}.csv");
        if (! preg_match('/^[a-z0-9_-]+$/', $section) || ! is_file($path)) {
            $this->error("لا يوجد ملف: database/data/kitchen/{$section}.csv");

            return self::FAILURE;
        }

        $file = KitchenRecipeFile::parse((string) file_get_contents($path));
        foreach ($file['problems'] as $dish => $problems) {
            foreach ($problems as $problem) {
                $this->warn("✗ {$dish}: {$problem}");
            }
        }

        $valid = array_values(array_filter($file['dishes'], fn ($d) => ! isset($file['problems'][$d['name']])));
        foreach ($importer->import($valid) as $row) {
            match ($row['result']) {
                'added' => $this->info("✓ {$row['dish']}: أُضيفت (تنتظر مراجعتك واعتمادك)"),
                'exists' => $this->line("• {$row['dish']}: موجودة من قبل، لم تُلمس"),
                default => $this->warn("✗ {$row['dish']}: {$row['reason']}"),
            };
        }

        return self::SUCCESS;
    }
}
