<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Images used to land on the private "local" disk, where the browser cannot reach them.
 * They now go to the public disk; copy the ones already uploaded (the originals stay).
 */
return new class extends Migration
{
    private const COLUMNS = [
        'brands' => 'logo',
        'products' => 'img',
        'foods' => 'img',
    ];

    public function up(): void
    {
        $from = Storage::disk('local');
        $to = Storage::disk('public');

        foreach (self::COLUMNS as $table => $column) {
            DB::table($table)->whereNotNull($column)->pluck($column)->each(function (string $path) use ($from, $to) {
                if (! $to->exists($path) && $from->exists($path)) {
                    $to->writeStream($path, $from->readStream($path));
                }
            });
        }
    }

    public function down(): void
    {
        // Copies only: nothing to undo.
    }
};
