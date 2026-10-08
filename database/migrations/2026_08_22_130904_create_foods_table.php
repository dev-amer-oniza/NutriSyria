<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A food is a CONCEPT ("chickpeas", "tabbouleh"). Its cooked/raw variants and its
        // nutrient numbers live in food_source_records. Dish vs ingredient is not stored:
        // a food is a dish when it has a recipe (computed, never a column).
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar')->index();
            $table->string('name_en')->nullable();
            $table->string('scientific_name')->nullable(); // Latin binomial for plants/animals (INFOODS good practice)
            $table->foreignId('food_category_id')->nullable()->constrained('food_categories')->nullOnDelete();
            $table->string('img')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
