<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a restaurant's public page shows besides its name, logo and phones (the owner's
 * decision 1a): governorate, address, opening hours, WhatsApp, social links and a delivery
 * note. All optional; an empty one is simply not shown.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'city' => 32, 'address' => 200, 'opening_hours' => 200, 'whatsapp' => 32,
        'instagram_url' => 255, 'facebook_url' => 255, 'delivery_note' => 200,
    ];

    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $length) {
                if (! Schema::hasColumn('brands', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }
};
