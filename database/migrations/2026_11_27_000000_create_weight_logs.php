<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "يومي": the person's weight, once a week or whenever they weigh (one row per day: weighing
 * twice in a day keeps the last). The newest also becomes the profile's weight, so the daily
 * needs follow it. Times are DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The person's own calendar day (config site.daily.timezone).
            $table->date('weighed_on');
            $table->decimal('weight_kg', 5, 1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'weighed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_logs');
    }
};
