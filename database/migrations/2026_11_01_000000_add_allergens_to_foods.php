<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allergens in each food (App\Enums\Allergen values), marked in the panel. A meal holding a
 * person's allergen is "not for you now" (MealFitCalculator). `allergens_checked` tells an
 * empty list ("none") from a food nobody has checked yet: until every ingredient is checked,
 * people with allergies are told the meal was not checked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            if (! Schema::hasColumn('foods', 'allergens')) {
                $table->json('allergens')->nullable()->after('img');
            }
            if (! Schema::hasColumn('foods', 'allergens_checked')) {
                $table->boolean('allergens_checked')->default(false)->after('allergens');
            }
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn(['allergens', 'allergens_checked']);
        });
    }
};
