<?php

use App\Enums\RecipeStatus;
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
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('servings')->default(1);
            $table->decimal('weight_before_cooking_g', 10, 2)->nullable();
            $table->decimal('weight_after_cooking_g', 10, 2)->nullable();
            $table->string('status', 24)->default(RecipeStatus::Draft->value)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_recipes');
    }
};
