<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * How many days a week the person trains in all, asked only when they write two exercise rows
 * or more (the owner's decision, Nov 2026): the rows cannot tell whether two sports share a day.
 * It picks the sport template only; the calories still come from each row's minutes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('training_days')->nullable()->after('exercises');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('training_days');
        });
    }
};
