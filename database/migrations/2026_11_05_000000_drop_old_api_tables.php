<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The old mobile API is gone (the owner's decisions 2a 4a): its Sanctum tokens and its OTP
 * codes go with it, so no token issued by the old /api/login can be used again. Sign-in codes
 * now live in auth_codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('otp_codes');
    }

    public function down(): void
    {
        // Nothing to restore: the code that used these tables was removed.
    }
};
