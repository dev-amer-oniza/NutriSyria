<?php

namespace App\Console\Commands;

use App\Services\Import\BundleImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportCommit extends Command
{
    protected $signature = 'import:commit {batch : Batch id printed by import:bundle}';

    protected $description = 'Save the ready records of a validated batch as pending_review';

    public function handle(BundleImporter $importer): int
    {
        $batchId = (int) $this->argument('batch');

        try {
            $s = $importer->commit($batchId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Batch #{$batchId} committed. Everything is saved as pending_review (it does not count until approved).");
        $this->line("Records:  {$s['records_created']} created | {$s['records_updated']} updated | {$s['records_unchanged']} unchanged | {$s['records_skipped']} skipped (blocked)");
        $this->line("Foods:    {$s['foods_created']} new (inactive until approved)");
        $this->line("Values:   {$s['values_written']} written | {$s['values_unchanged']} unchanged");
        $this->line("Portions: {$s['portions_written']} written");

        if ($s['changed_values']) {
            $this->newLine();
            $this->line('Values that changed compared with what was stored:');
            $this->table(['external_ref', 'nutrient', 'old', 'new'], array_map(fn ($c) => [$c['external_ref'], $c['nutrient'], $c['old'], $c['new']], array_slice($s['changed_values'], 0, 30)));
        }

        $this->newLine();
        $this->line("Next: review, then php artisan import:approve {$batchId}");

        return self::SUCCESS;
    }
}
