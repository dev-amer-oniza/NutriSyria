<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who approved or rejected an imported record, who approved a product's official nutrition
 * values, and when. Kept when the user is deleted (the name is gone, the date stays).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_source_records', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });

        Schema::table('product_recipes', function (Blueprint $table) {
            $table->foreignId('nutrition_approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('nutrition_approved_at')->nullable()->after('nutrition_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('product_recipes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nutrition_approved_by');
            $table->dropColumn('nutrition_approved_at');
        });

        Schema::table('food_source_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });
    }
};
