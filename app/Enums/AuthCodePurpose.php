<?php

namespace App\Enums;

/** What an emailed 6-digit code is for. One live code per user and purpose. */
enum AuthCodePurpose: string
{
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';

    public function subject(): string
    {
        return match ($this) {
            self::VerifyEmail => 'رمز تأكيد بريدك في NutriSyria',
            self::ResetPassword => 'رمز إعادة تعيين كلمة المرور في NutriSyria',
        };
    }

    public function intro(): string
    {
        return match ($this) {
            self::VerifyEmail => 'أدخل هذا الرمز لتأكيد بريدك الإلكتروني وإكمال إنشاء حسابك.',
            self::ResetPassword => 'أدخل هذا الرمز لتعيين كلمة مرور جديدة لحسابك.',
        };
    }
}
