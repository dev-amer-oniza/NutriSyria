<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person asking a nutritionist for a consultation, and the link that follows if it is
 * accepted (App\Services\Consultations\ConsultationService). One open request or link per
 * person at a time; a pending one expires after 7 days. Times are DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nutritionist_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('status', 16);
            $table->string('decline_reason', 300)->nullable();
            $table->dateTime('consented_at');
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['nutritionist_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_requests');
    }
};
