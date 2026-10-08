<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrients', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();                  // our stable key: energy_kcal, protein_g, iron_mg
            // FAO/INFOODS component identifier. NOT unique: INFOODS distinguishes some
            // components by unit only (energy in kcal and kJ both use ENERC).
            $table->string('infoods_tagname', 32)->nullable()->index();
            $table->string('name_ar');
            $table->string('name_en');
            // Storage unit for every food_nutrient_values row of this nutrient. Never change it
            // once values exist; source unit differences are handled by nutrient_source_codes.factor.
            $table->string('unit', 12);                            // kcal, kJ, g, mg, ug
            $table->string('group', 24)->index();                  // energy, proximate, carbohydrate, fat, mineral, vitamin
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_core')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // usda_nutrient_id is gone on purpose: source codes live in nutrient_source_codes.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrients');
    }
};
