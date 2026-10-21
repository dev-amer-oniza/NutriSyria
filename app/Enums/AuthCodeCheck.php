<?php

namespace App\Enums;

/** The result of checking an emailed code. */
enum AuthCodeCheck
{
    case Valid;
    case Invalid;
    case Expired;
    case TooManyAttempts;

    public function message(): string
    {
        return match ($this) {
            self::Valid => '',
            self::Invalid => 'الرمز غير صحيح.',
            self::Expired => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.',
            self::TooManyAttempts => 'محاولات كثيرة بهذا الرمز. اطلب رمزاً جديداً.',
        };
    }
}
