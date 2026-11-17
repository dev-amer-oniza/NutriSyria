<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each ingredient brings to the whole recipe, by nutrient (step 4 of the medical plan,
 * decision 1a): written with the official values when they are approved, so "من أين؟" always
 * explains the numbers on the site. Fill the meals approved before with meals:contributions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->json('contributions')->nullable()->after('nutrition_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropColumn('contributions');
        });
    }
};
