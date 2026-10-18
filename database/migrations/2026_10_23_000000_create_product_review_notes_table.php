<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes the nutritionist (or admin) sends a brand owner about a product's analysis: "the weight
 * after cooking is missing". A note stays open until the owner marks it fixed (with an
 * optional reply) or its author withdraws it; official values cannot be approved meanwhile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_review_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();     // the owner's reply, or why it was withdrawn
            $table->timestamps();

            $table->index(['product_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_review_notes');
    }
};
