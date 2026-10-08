<?php

namespace App\Console\Commands;

use App\Services\Import\BundleImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportBundle extends Command
{
    protected $signature = 'import:bundle
        {path : Folder containing records.csv, nutrient_values.csv and portions.csv}
        {--force : Import again even if this exact bundle was already committed}';

    protected $description = 'Stage and validate an import bundle (nothing is saved to the food tables)';

    public function handle(BundleImporter $importer): int
    {
        try {
            $batchId = $importer->stage($this->argument('path'), null, (bool) $this->option('force'));
            $report = $importer->validate($batchId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $r = $report['records'];
        $this->newLine();
        $this->info("Batch #{$batchId} validated. Nothing has been saved to the food tables yet.");
        $this->line("Records:  {$r['total']} total | {$r['ready']} ready | {$r['blocked']} blocked | {$r['new_foods']} new foods | {$r['existing_records']} already exist (will be updated)");
        $this->line("Values:   {$report['values']['total']} rows | {$report['values']['ok']} ok | {$report['values']['errors']} errors");
        $this->line("Portions: {$report['portions']['total']} rows | {$report['portions']['ok']} ok | {$report['portions']['errors']} errors (portion errors never block a record)");

        foreach (['errors' => 'Errors', 'warnings' => 'Warnings'] as $key => $label) {
            if ($report[$key]) {
                $this->line($label.': '.implode(', ', array_map(fn ($c, $n) => "{$c} x{$n}", array_keys($report[$key]), $report[$key])));
            }
        }
        if ($report['unmapped_unit_labels']) {
            $this->line('Unit labels to map once (measure_unit_aliases): '.implode(', ', array_map(fn ($l, $n) => "{$l} x{$n}", array_keys($report['unmapped_unit_labels']), $report['unmapped_unit_labels'])));
        }

        if ($report['issues']) {
            $this->newLine();
            $this->table(
                ['Sheet', 'Row', 'Level', 'Code', 'Message'],
                array_map(fn ($i) => [$i['sheet'], $i['row'], $i['level'], $i['code'], $i['message']], $report['issues'])
            );
            if ($report['issues_total'] > count($report['issues'])) {
                $this->line('... showing '.count($report['issues'])." of {$report['issues_total']} issues (row numbers match the CSV file rows).");
            }
        }

        $this->newLine();
        $this->line("Next: php artisan import:commit {$batchId}");

        return self::SUCCESS;
    }
}
