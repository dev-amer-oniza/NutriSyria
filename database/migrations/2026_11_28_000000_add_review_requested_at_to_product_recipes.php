<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the nutritionists were told a product waits for them (ProductNotices): once per review,
 * cleared when its values are approved, so editing a recipe line by line tells them only once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dateTime('review_requested_at')->nullable()->after('nutrition_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropColumn('review_requested_at');
        });
    }
};
