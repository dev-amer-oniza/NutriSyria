<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_recipe_id')->constrained('product_recipes')->cascadeOnDelete();
            $table->foreignId('food_source_record_id')->constrained('food_source_records')->restrictOnDelete();
            $table->foreignId('food_form_id')->nullable()->constrained('food_forms')->nullOnDelete();
            $table->foreignId('measure_unit_id')->constrained('measure_units')->restrictOnDelete();
            $table->decimal('amount', 12, 4);
            $table->boolean('is_added_after_cooking')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_recipe_id', 'sort_order'], 'product_recipe_ingredient_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_recipe_ingredients');
    }
};
