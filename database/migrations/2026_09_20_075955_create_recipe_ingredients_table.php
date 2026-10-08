<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('recipes')->cascadeOnDelete();
            // FIXED vs the old schema: no unique() here (an ingredient record such as olive
            // oil must be usable in many recipes) and restrictOnDelete (deleting a source
            // record must never silently remove ingredients from recipes).
            $table->foreignId('food_source_record_id')->constrained('food_source_records')->restrictOnDelete();
            // Must equal the source record's food_form_id or be NULL (checked by the app).
            $table->foreignId('food_form_id')->nullable()->constrained('food_forms')->nullOnDelete();
            $table->foreignId('measure_unit_id')->constrained('measure_units')->restrictOnDelete();
            $table->decimal('amount', 12, 4);                  // in measure_unit_id; grams resolved through food_portions
            // True for ingredients added after cooking (finishing oil): excluded from the pre-cook weight.
            $table->boolean('is_added_after_cooking')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['recipe_id', 'sort_order'], 'recipe_ingredient_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
