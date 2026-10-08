<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "1 cup of chickpeas = 164 g": what turns a restaurant's household measures into grams.
        Schema::create('food_portions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->foreignId('measure_unit_id')->constrained('measure_units')->restrictOnDelete();
            $table->decimal('amount', 12, 4)->default(1);
            $table->decimal('gram_weight', 14, 6);
            $table->string('description')->nullable();         // source wording, e.g. "1 cup, chopped"
            $table->foreignId('food_source_record_id')->nullable()->constrained('food_source_records')->nullOnDelete();
            $table->foreignId('food_form_id')->nullable()->constrained('food_forms')->nullOnDelete();
            // reference | usda | measured | estimated | restaurant_measured  (string + PHP enum cast)
            $table->string('basis', 24)->default('reference')->index();
            // calculated | reference | local_reference | measured | reviewed | verified
            $table->string('confidence_level', 24)->default('reference')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamp('valid_from')->nullable()->index();
            $table->timestamp('valid_to')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->index(['food_id', 'measure_unit_id', 'food_form_id'], 'food_portion_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_portions');
    }
};
