<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The site signs up plain users only now (the owner's decision): nobody says at sign-up
 * whether they run a restaurant or a brand. The admin sets it on the client (brands.kind).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'brand_kind')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('brand_kind');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('brand_kind', 20)->nullable()->after('requested_role');
        });
    }
};
