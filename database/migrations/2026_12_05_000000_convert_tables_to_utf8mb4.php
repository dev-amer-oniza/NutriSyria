<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every table in utf8mb4 (the owner's report: approving a meal on the server failed with
 * "Incorrect string value" for food_nutrient_values.notes, an Arabic note in a table that was not
 * utf8mb4; XAMPP's non-strict mode had been saving such text as "???" without a word). Converts
 * only the tables that are not utf8mb4 yet, text and all, so running it twice does nothing.
 * MySQL and MariaDB only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $database = DB::getDatabaseName();
        $tables = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->where('TABLE_COLLATION', 'not like', 'utf8mb4%')
            ->pluck('TABLE_NAME');

        // Also tables whose own default is utf8mb4 but with an older column inside.
        $tables = $tables->merge(DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->whereNotNull('CHARACTER_SET_NAME')
            ->where('CHARACTER_SET_NAME', '!=', 'utf8mb4')
            ->distinct()
            ->pluck('TABLE_NAME'))->unique()->values();

        DB::statement("ALTER DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Back to a narrower character set would break the Arabic text: nothing to undo.
    }
};
