<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A site user's nutrition profile and the criteria their meals are judged against.
 *
 * - user_profiles: body, activity, goal, and the optional health step. Conditions and
 *   allergies are sensitive and stored encrypted (TEXT, Laravel "encrypted:array").
 * - user_criteria: values a person or their nutritionist set instead of the computed default,
 *   one row per criterion and source.
 * - user_criteria_changes: who changed which criterion, from what, to what, and when.
 *
 * Times are DATETIME (not TIMESTAMP) so the tables are accepted on XAMPP MySQL/MariaDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('sex', 8)->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('activity_level', 16)->nullable();
            $table->string('goal', 16)->nullable();
            $table->text('health_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->dateTime('health_consent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('user_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('criterion', 48);
            $table->string('source', 16);
            $table->decimal('value', 10, 2);
            $table->text('note')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'criterion', 'source']);
        });

        Schema::create('user_criteria_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('criterion', 48);
            $table->string('source', 16);
            $table->decimal('old_value', 10, 2)->nullable();
            $table->decimal('new_value', 10, 2)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_criteria_changes');
        Schema::dropIfExists('user_criteria');
        Schema::dropIfExists('user_profiles');
    }
};
