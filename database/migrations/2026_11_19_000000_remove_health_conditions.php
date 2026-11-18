<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The platform serves healthy adults and people who train, with their allergies (the owner's
 * decision, Nov 2026). The health conditions people wrote are sensitive data with no use any
 * more, so they are erased (decision 2a); the recovery reminder they fed goes with them.
 * People no longer set their own criteria or template (decision 3a): those rows go too, and
 * the nutritionist's stay. The change log is kept. Not reversible: the data is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['health_conditions', 'recovery_reminded_at']);
        });

        DB::table('user_criteria')->whereIn('source', ['user', 'doctor'])->delete();
        DB::table('user_diet_templates')->whereIn('source', ['user', 'doctor'])->delete();
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->text('health_conditions')->nullable()->after('goal');
            $table->dateTime('recovery_reminded_at')->nullable();
        });
    }
};
