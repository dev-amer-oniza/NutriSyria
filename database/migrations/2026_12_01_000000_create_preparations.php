<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "تحضيراتي": a brand's own preparations (كريمة تمر = تمر + مي), written once and used as an
 * ingredient in its products (the owner's decisions 1a 2a 3a 4a). Brands and restaurants only,
 * one level (a preparation holds foods, never another preparation). A product line names
 * either a food record or a preparation; the analysis opens the preparation into its foods.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->string('name');
            $table->string('note', 300)->nullable();
            // The batch's weight once made; empty = the sum of its ingredients.
            $table->decimal('weight_after_g', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'name']);
        });

        Schema::create('preparation_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('preparations')->cascadeOnDelete();
            $table->foreignId('food_source_record_id')->constrained('food_source_records')->restrictOnDelete();
            $table->foreignId('measure_unit_id')->constrained('measure_units')->restrictOnDelete();
            $table->foreignId('food_portion_id')->nullable()->constrained('food_portions')->nullOnDelete();
            $table->decimal('amount', 12, 4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('product_recipe_ingredients', function (Blueprint $table) {
            $table->foreignId('food_source_record_id')->nullable()->change();
            // A preparation in use cannot be deleted (the owner's decision 2a): the page says
            // which products use it; the database refuses as a last guard.
            $table->foreignId('preparation_id')->nullable()->after('food_source_record_id')
                ->constrained('preparations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_recipe_ingredients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preparation_id');
        });
        Schema::dropIfExists('preparation_ingredients');
        Schema::dropIfExists('preparations');
    }
};
