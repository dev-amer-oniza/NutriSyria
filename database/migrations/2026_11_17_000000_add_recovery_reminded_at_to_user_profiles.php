<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the person was last asked whether a passing condition ("عدم تحمّل الدسم", jaundice)
 * still holds (App\Services\Nutrition\RecoveryReminder). Null while they have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dateTime('recovery_reminded_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('recovery_reminded_at');
        });
    }
};
