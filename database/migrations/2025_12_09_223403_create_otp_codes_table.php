<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            // البريد الإلكتروني المرتبط بالـ OTP
            $table->string('email')->index();

            // الـ OTP مخزن كهاش (مش الرقم نفسه)
            $table->string('otp_hash');

            // الغرض من الـ OTP (reset_password, register, email_verification, ...)
            $table->string('purpose')->index();

            // تاريخ انتهاء صلاحية الـ OTP
            $table->timestamp('expires_at')->index();

            // لحفظ تاريخ الإنشاء والتحديث
            $table->timestamps();

            // لمنع وجود أكثر من OTP بنفس الغرض والبريد في نفس الوقت
            $table->unique(['email', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
