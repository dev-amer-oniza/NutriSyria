<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table that makes "any source" cheap: a new source (USDA release, national table,
        // lab template) is onboarded by mapping its component codes here, not by changing code.
        Schema::create('nutrient_source_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nutrient_id')->constrained('nutrients')->cascadeOnDelete();
            $table->string('source_system', 32);          // usda_fdc, infoods, ... (string, not enum)
            $table->string('code', 32);                   // the source's own component code (FDC nutrient.id)
            // source amount x factor = amount in nutrients.unit. Use 1 when units already match.
            $table->decimal('factor', 14, 6)->default(1);
            // Several source codes may feed one nutrient (FDC has "Total Sugars" and
            // "Sugars, total including NLEA"; Foundation energy is Atwater-based).
            // When a food carries more than one, the lowest priority number wins.
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();

            $table->unique(['source_system', 'code'], 'nutrient_source_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrient_source_codes');
    }
};
