<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportReview extends Command
{
    protected $signature = 'import:review {batch : Batch id}';

    protected $description = 'List the records a batch saved, with their key nutrient values, so they can be reviewed before approval';

    private const KEY_NUTRIENTS = ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g'];

    public function handle(): int
    {
        $batchId = (int) $this->argument('batch');

        $records = DB::table('food_source_records as r')
            ->join('foods as f', 'f.id', '=', 'r.food_id')
            ->leftJoin('food_forms as ff', 'ff.id', '=', 'r.food_form_id')
            ->where('r.import_batch_id', $batchId)
            ->orderBy('f.name_en')->orderBy('ff.code')
            ->get(['r.id', 'f.name_en', 'ff.code as form', 'r.external_ref', 'r.status']);

        if ($records->isEmpty()) {
            $this->warn("Batch {$batchId} has no saved records (was it committed?).");

            return self::FAILURE;
        }

        $ids = $records->pluck('id')->all();

        $values = [];
        $rows = DB::table('food_nutrient_values as v')
            ->join('nutrients as n', 'n.id', '=', 'v.nutrient_id')
            ->whereIn('v.food_source_record_id', $ids)
            ->whereIn('n.code', self::KEY_NUTRIENTS)
            ->get(['v.food_source_record_id', 'n.code', 'v.amount_per_100g']);
        foreach ($rows as $v) {
            $values[$v->food_source_record_id][$v->code] = rtrim(rtrim(number_format((float) $v->amount_per_100g, 2, '.', ''), '0'), '.');
        }

        $portionCounts = DB::table('food_portions')
            ->whereIn('food_source_record_id', $ids)
            ->selectRaw('food_source_record_id, count(*) as c')
            ->groupBy('food_source_record_id')
            ->pluck('c', 'food_source_record_id');

        $this->table(
            ['Food', 'Form', 'FDC id', 'Status', 'kcal', 'Protein g', 'Fat g', 'Carb g', 'Portions'],
            $records->map(fn ($r) => [
                $r->name_en, $r->form, $r->external_ref, $r->status,
                $values[$r->id]['energy_kcal'] ?? '-', $values[$r->id]['protein_g'] ?? '-',
                $values[$r->id]['fat_g'] ?? '-', $values[$r->id]['carbohydrate_g'] ?? '-',
                $portionCounts[$r->id] ?? 0,
            ])->all()
        );

        $byStatus = $records->countBy('status')->map(fn ($n, $s) => "{$s}: {$n}")->implode(' | ');
        $this->line("Values are per 100 g. {$byStatus}");
        $this->line("When you are satisfied: php artisan import:approve {$batchId}");

        return self::SUCCESS;
    }
}
