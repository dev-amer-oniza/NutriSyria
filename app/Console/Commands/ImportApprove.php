<?php

namespace App\Console\Commands;

use App\Services\Import\BundleImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportApprove extends Command
{
    protected $signature = 'import:approve {batch : Batch id}';

    protected $description = 'Approve EVERY pending_review record of a committed batch (makes it count in nutrition results)';

    public function handle(BundleImporter $importer): int
    {
        $batchId = (int) $this->argument('batch');

        if (! $this->confirm("Approve all pending records of batch #{$batchId}? Only do this after you reviewed the validation report and the data.")) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $s = $importer->approve($batchId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Approved {$s['records_approved']} records; activated {$s['foods_activated']} foods.");

        return self::SUCCESS;
    }
}
