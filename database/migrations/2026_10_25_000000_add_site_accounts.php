<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts created on the public site.
 *
 * - users.requested_role: a brand owner or nutritionist who signed up and waits for the
 *   super-admin to grant the role (null once granted or declined). A plain site user has
 *   no role at all, so the panel stays closed to them.
 * - auth_codes: the 6-digit codes sent by email (verify the address, reset the password),
 *   stored hashed, one live code per user and purpose.
 *
 * Its times are DATETIME, not TIMESTAMP: on MySQL/MariaDB servers with
 * explicit_defaults_for_timestamp off (XAMPP), a second NOT NULL TIMESTAMP gets an invalid
 * zero default and the table is refused. A first run that failed there had already added the
 * users columns, so each step checks before it runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'requested_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('requested_role', 32)->nullable()->index()->after('is_active');
            });
        }
        if (! Schema::hasColumn('users', 'role_requested_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('role_requested_at')->nullable()->after('requested_role');
            });
        }

        Schema::dropIfExists('auth_codes'); // never holds data before this migration succeeds
        Schema::create('auth_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('sent_at');
            $table->timestamps();

            $table->unique(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_codes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['requested_role', 'role_requested_at']);
        });
    }
};
