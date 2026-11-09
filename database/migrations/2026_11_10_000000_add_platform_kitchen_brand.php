<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "المطبخ السوري": the platform's own dishes (the owner's decisions 1 2a 3a 4a). It is stored as
 * one special brand, so dishes reuse products, recipes, categories and the analysis; it never
 * shows among the restaurants, and the admin alone manages and approves it, with no review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('is_platform')->default(false)->after('is_active');
        });

        DB::table('brands')->insert([
            'name' => 'المطبخ السوري',
            'description' => 'أكلات البيت السوري بقيمها الغذائية، من وصفات المنصة.',
            'is_active' => true,
            'is_platform' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('brands')->where('is_platform', true)->delete();
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('is_platform');
        });
    }
};
