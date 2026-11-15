<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tables of the old system nothing reads any more (the owner's decision 1a): the first
 * recipe model (dishes now live in products / product_recipes) and the ShamCash accounts.
 * They are dropped only while empty, so no data is lost by surprise.
 */
return new class extends Migration
{
    private const TABLES = ['recipe_ingredients', 'recipes', 'sham_cash_accounts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("The old table '{$table}' still holds rows: back them up or empty it, then migrate again.");
            }
        }

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Nothing to restore: the code that used these tables was removed.
    }
};
