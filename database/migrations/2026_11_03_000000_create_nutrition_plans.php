<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A nutritionist's plan for one client link (App\Services\Consultations\ClientWorkspace):
 * the equation, the calorie change, the macro shares and the fluids. Saving it sets the
 * client's calories and macros as the nutritionist's values. Times are DATETIME (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_request_id')->unique()->constrained('consultation_requests')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('equation', 24);
            $table->decimal('body_fat_pct', 4, 1)->nullable();
            $table->smallInteger('adjustment_kcal');
            $table->unsignedTinyInteger('carbs_pct');
            $table->unsignedTinyInteger('protein_pct');
            $table->unsignedTinyInteger('fat_pct');
            $table->unsignedSmallInteger('fluids_ml')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_plans');
    }
};
