<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The diet template a person or their nutritionist chose (config/nutrition_criteria.php
 * 'templates'); without one, the template comes from the person's conditions. One row per
 * source, like user_criteria. The change log gains text columns for template changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_diet_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 16);
            $table->string('template', 48);
            $table->text('note')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_id', 'source']);
        });

        Schema::table('user_criteria_changes', function (Blueprint $table) {
            $table->string('old_text', 48)->nullable()->after('new_value');
            $table->string('new_text', 48)->nullable()->after('old_text');
        });
    }

    public function down(): void
    {
        Schema::table('user_criteria_changes', function (Blueprint $table) {
            $table->dropColumn(['old_text', 'new_text']);
        });
        Schema::dropIfExists('user_diet_templates');
    }
};
