<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maps the free-text unit labels sources use ("tablespoon", "tbsp", "ملعقة كبيرة")
        // to one measure unit. An unmapped label is reported by the importer and mapped once.
        Schema::create('measure_unit_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('measure_unit_id')->constrained('measure_units')->cascadeOnDelete();
            $table->string('label', 64)->unique(); // stored normalized: trimmed + lowercased
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measure_unit_aliases');
    }
};
