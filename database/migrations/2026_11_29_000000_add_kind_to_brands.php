<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant or brand (App\Enums\BrandKind). Every place so far stays a restaurant. The owner's
 * own answer at sign-up is kept on the user, for the admin to see when setting the brand's kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('kind', 20)->default('restaurant')->after('name')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('brand_kind', 20)->nullable()->after('requested_role');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('brand_kind');
        });
    }
};
