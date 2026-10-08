<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every raw row of every import, kept forever: the evidence behind each stored value.
        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->string('sheet', 24);                      // records, nutrient_values, portions
            $table->unsignedInteger('row_number');
            $table->json('raw');
            $table->string('status', 16)->default('pending'); // pending, valid, error, committed, skipped
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'sheet', 'status'], 'import_row_batch_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
