<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per uploaded import bundle: who, when, which file (hash), what happened.
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->char('file_sha256', 64)->index();       // same hash = same file uploaded again
            $table->string('status', 16)->default('uploaded')->index(); // uploaded, validated, committed, failed
            $table->json('stats')->nullable();               // counts, warnings, changed-values report
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
