<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One source's description of ONE food in ONE form (e.g. USDA item 173757 =
        // chickpeas, boiled). Source type, name, country and priority are NOT stored here:
        // they are derived from data_sources.
        Schema::create('food_source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->foreignId('food_form_id')->nullable()->constrained('food_forms')->nullOnDelete();
            // Required: every number in the database must say where it came from.
            $table->foreignId('data_source_id')->constrained('data_sources')->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('external_ref', 128)->nullable();   // FDC id, lab report number, table row ref
            // active | superseded | rejected | pending_review  (string + PHP enum cast, not a DB enum)
            $table->string('status', 24)->default('active')->index();
            $table->boolean('is_preferred')->default(false)->index(); // per-food override of source ranking
            // Share of the as-purchased weight that is NOT edible, as the source reports it.
            // Stored now so nothing is lost; conversion logic comes later.
            $table->decimal('refuse_pct', 5, 2)->nullable();
            $table->timestamp('valid_from')->nullable()->index();
            $table->timestamp('valid_to')->nullable()->index();
            $table->timestamp('imported_at')->nullable();
            $table->string('img')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // The crosswalk: the same source item always maps to the same record, so
            // re-importing a new release finds and updates it. NULL external_ref (manual
            // entries) is allowed more than once.
            $table->unique(['data_source_id', 'external_ref'], 'food_source_item_unique');
            $table->index(['food_id', 'food_form_id', 'status'], 'food_source_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_source_records');
    }
};
