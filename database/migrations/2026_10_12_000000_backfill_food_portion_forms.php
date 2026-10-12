<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Portions entered on a source record's page were saved without a form: they borrowed the
     * record's form only while still linked to it. Copy the form onto the portion so a later
     * dissociate or record delete can no longer make it apply to every form of the food.
     */
    public function up(): void
    {
        DB::statement(
            'UPDATE food_portions SET food_form_id = ('.
            'SELECT r.food_form_id FROM food_source_records r WHERE r.id = food_portions.food_source_record_id'.
            ') WHERE food_form_id IS NULL AND food_source_record_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        // Not reversible: a form that was copied cannot be told apart from one set by hand.
    }
};
