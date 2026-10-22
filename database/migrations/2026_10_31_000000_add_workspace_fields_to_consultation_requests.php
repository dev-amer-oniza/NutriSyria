<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The nutritionist's workspace ("my clients"): why a link was ended (emailed to the person),
 * and when the person was last emailed about criteria changes (one email per editing
 * session, App\Services\Consultations\ClientWorkspace). DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('consultation_requests', 'end_reason')) {
                $table->string('end_reason', 300)->nullable()->after('ended_by');
            }
            if (! Schema::hasColumn('consultation_requests', 'client_notified_at')) {
                $table->dateTime('client_notified_at')->nullable()->after('end_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultation_requests', function (Blueprint $table) {
            $table->dropColumn(['end_reason', 'client_notified_at']);
        });
    }
};
