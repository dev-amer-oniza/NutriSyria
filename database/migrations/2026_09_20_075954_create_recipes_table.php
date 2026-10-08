<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dish's composition IS a source record (data source: internal recipes), so its
        // calculated nutrients live in food_nutrient_values like any other source.
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_source_record_id')->unique()
                ->constrained('food_source_records')->cascadeOnDelete();
            $table->unsignedSmallInteger('servings')->default(1);
            $table->decimal('weight_before_cooking_g', 10, 2)->nullable();
            $table->decimal('weight_after_cooking_g', 10, 2)->nullable();
            $table->string('status', 24)->default('draft')->index(); // draft, published, ... (PHP enum cast)
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
