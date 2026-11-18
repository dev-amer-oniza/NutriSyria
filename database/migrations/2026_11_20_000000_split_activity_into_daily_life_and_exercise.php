<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The activity question is split in two (the owner's decision, Nov 2026): how the day goes
 * without exercise (daily_life, with work_days for a job or study on some days only), and the
 * exercise itself (exercises: [{type, times, minutes}], config nutrition_criteria.exercises).
 * The old single level mixed both and cannot be mapped: it is dropped, and people answer the
 * activity step again (their profile's next step, with the "complete your profile" banner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('daily_life', 16)->nullable()->after('weight_kg');
            $table->unsignedTinyInteger('work_days')->nullable()->after('daily_life');
            $table->json('exercises')->nullable()->after('work_days');
        });
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('activity_level');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('activity_level', 16)->nullable()->after('weight_kg');
        });
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['daily_life', 'work_days', 'exercises']);
        });
    }
};
