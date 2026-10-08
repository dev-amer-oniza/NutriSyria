<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('food_categories')->nullOnDelete();
            // Bilingual like foods: USDA/INFOODS category names arrive in English, the UI is Arabic.
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->timestamps();

            // Imports resolve categories by path ("legumes > chickpeas"), so a name must be
            // unique under its parent. (MySQL treats NULL parents as distinct: the importer
            // must also refuse duplicate root names.)
            $table->unique(['parent_id', 'name_ar'], 'food_category_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_categories');
    }
};
