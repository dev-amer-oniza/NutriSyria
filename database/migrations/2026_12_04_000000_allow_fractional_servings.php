<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servings may be a fraction (the owner's request): a 1700 g pot sold as 200 g portions is
 * 8.5 portions. Existing whole numbers stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->decimal('servings', 6, 2)->default(1)->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->unsignedSmallInteger('servings')->default(1)->change();
        });
    }
};
