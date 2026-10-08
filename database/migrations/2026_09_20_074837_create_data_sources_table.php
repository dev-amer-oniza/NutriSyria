<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per dataset you trust: USDA SR Legacy, USDA Foundation Foods, a national
        // table, a lab, your own recipes. Provenance, licence and TRUST RANKING live here,
        // so re-ranking a source is one row update instead of thousands.
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();          // usda_fdc_sr_legacy
            $table->string('name');
            $table->string('type', 32)->index();           // usda_fdc, national_table, lab, recipe, manual ,internal_product_recipes
            $table->string('publisher')->nullable();
            $table->string('version', 64)->nullable();     // release identifier, e.g. 2018-04
            $table->date('published_at')->nullable();
            $table->string('country', 12)->nullable();     // where the data comes from
            $table->string('url')->nullable();
            $table->string('license')->nullable();
            $table->text('citation')->nullable();
            // Source resolution: lower number wins (e.g. Syrian lab data 10, local table 50, USDA 100).
            $table->unsignedSmallInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
