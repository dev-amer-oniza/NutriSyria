<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "يومي": what a person ate each day (meal_logs), and the dishes they could not find and
 * asked us to add (food_requests). A logged meal keeps the values it had when eaten, so a
 * later change to the recipe never rewrites someone's past day. Times are DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            // The name with Arabic spelling variants folded (ArabicNormalizer): requests for one dish meet here.
            $table->string('name_key', 120)->index();
            $table->string('ingredients', 500);
            $table->string('status', 16)->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('admin_note', 300)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The person's own calendar day (config site.daily.timezone), not the server's.
            $table->date('eaten_on');
            $table->dateTime('eaten_at');
            $table->string('meal_type', 16);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('food_request_id')->nullable()->constrained('food_requests')->cascadeOnDelete();
            $table->string('name', 160);
            // Of the meal's plate (¼ … 1), or of 100 g when the meal has no plate weight.
            $table->decimal('amount', 4, 2)->default(1);
            $table->boolean('per_100g')->default(false);
            // The values eaten; null while a requested dish is not on the platform yet (not counted).
            $table->decimal('energy_kcal', 7, 1)->nullable();
            $table->decimal('protein_g', 6, 1)->nullable();
            $table->decimal('carbohydrate_g', 6, 1)->nullable();
            $table->decimal('fat_g', 6, 1)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['user_id', 'eaten_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
        Schema::dropIfExists('food_requests');
    }
};
