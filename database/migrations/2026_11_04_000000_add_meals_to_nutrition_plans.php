<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The plan's meals (phase 2): a list of {type, label, time, share_pct, carbs_g, protein_g,
 * fat_g}, by time. Null for a plan without meals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_plans', function (Blueprint $table) {
            $table->json('meals')->nullable()->after('fluids_ml');
        });
    }

    public function down(): void
    {
        Schema::table('nutrition_plans', function (Blueprint $table) {
            $table->dropColumn('meals');
        });
    }
};
