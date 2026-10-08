<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measure_units', function (Blueprint $table) {
            $table->id();
            // Now required: importers and aliases resolve units by code.
            $table->string('code', 32)->unique();               // g, kg, ml, tbsp, loaf
            $table->string('name_ar');                          // غرام، كيلوغرام، ملعقة كبيرة
            $table->string('name_en')->nullable();
            $table->string('dimension', 24)->nullable()->index(); // mass, volume, count, household
            // Only for physically fixed conversions to the dimension's base (mass -> g, volume -> ml).
            // Household units (cup, tbsp, piece, loaf) stay NULL: their gram weight comes from food_portions.
            $table->decimal('base_factor', 14, 6)->nullable();
            $table->string('img')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_units');
    }
};
