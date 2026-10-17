<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a recipe line name its portion size ("2 large onions") instead of relying on the food's
 * default portion. NULL = the usual portion rules (PortionResolver); a deleted portion falls
 * back to them too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_recipe_ingredients', function (Blueprint $table) {
            $table->foreignId('food_portion_id')->nullable()->after('measure_unit_id')
                ->constrained('food_portions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_recipe_ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('food_portion_id');
        });
    }
};
