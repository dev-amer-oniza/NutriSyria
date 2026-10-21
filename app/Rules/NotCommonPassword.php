<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps the password simple to choose but refuses the ones guessed first: well-known
 * passwords and a single repeated character or a plain run of digits.
 */
class NotCommonPassword implements ValidationRule
{
    private const COMMON = [
        '12345678', '123456789', '1234567890', '87654321', '11223344', '12341234', '12121212',
        'password', 'password1', 'password123', 'passw0rd', 'qwertyui', 'qwerty123', 'qwertyuiop',
        'abcd1234', 'abcdefgh', 'iloveyou', 'admin123', 'welcome1', 'letmein1', '1q2w3e4r',
        'asdfghjk', 'zxcvbnm1', 'aaaaaaaa', 'syria123', 'nutrisyria',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = mb_strtolower((string) $value);

        if (in_array($password, self::COMMON, true)
            || preg_match('/^(.)\1+$/u', $password)
            || $this->isDigitRun($password)) {
            $fail('كلمة المرور هذه سهلة التخمين. اختر كلمة أخرى.');
        }
    }

    /** 01234567, 98765432 and the like. */
    private function isDigitRun(string $password): bool
    {
        if (! ctype_digit($password)) {
            return false;
        }

        $up = $down = true;
        for ($i = 1, $n = strlen($password); $i < $n; $i++) {
            $step = (int) $password[$i] - (int) $password[$i - 1];
            $up = $up && ($step === 1 || $step === -9);
            $down = $down && ($step === -1 || $step === 9);
        }

        return $up || $down;
    }
}
