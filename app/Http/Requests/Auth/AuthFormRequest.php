<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\NotCommonPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Password;

/**
 * Shared input cleaning and rules for the site's account forms. Each form adds its own
 * fields; the email, password, code and bot-trap rules are written once here.
 */
abstract class AuthFormRequest extends FormRequest
{
    /** Hidden field real visitors never see; bots that fill every input are refused. */
    public const HONEYPOT = 'website';

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];

        if ($this->has('email')) {
            $clean['email'] = self::normalizeEmail($this->input('email'));
        }
        if ($this->has('code')) {
            $clean['code'] = preg_replace('/\D/', '', self::latinDigits((string) $this->input('code')));
        }

        $this->merge($clean);
    }

    public static function normalizeEmail(mixed $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public static function latinDigits(string $value): string
    {
        return str_replace(self::ARABIC_DIGITS, self::LATIN_DIGITS, $value);
    }

    /**
     * To one international form (+9639XXXXXXXX): Arabic digits, spaces and dashes,
     * a leading 00, and a Syrian local number (09XXXXXXXX) are all accepted.
     */
    public static function normalizeWhatsapp(mixed $number): ?string
    {
        $number = preg_replace('/[\s\-().]/u', '', self::latinDigits(trim((string) $number)));

        if ($number === '') {
            return null;
        }
        if (str_starts_with($number, '00')) {
            return '+'.substr($number, 2);
        }
        if (preg_match('/^09\d{8}$/', $number)) {
            return '+963'.substr($number, 1);
        }

        return $number;
    }

    /** An international number (after normalizeWhatsapp) not used by another account. */
    protected function whatsappRules(?User $owner = null): array
    {
        return ['string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique(User::class, 'phone')->ignore($owner)];
    }

    /** A strict, well-formed address; in production its domain must also receive mail. */
    protected function emailRules(): array
    {
        $email = Email::default()->rfcCompliant(strict: true)->preventSpoofing();

        if (app()->isProduction()) {
            $email->validateMxRecord();
        }

        return ['required', 'string', 'max:254', $email];
    }

    /**
     * Simple on purpose (the owner's choice): 8 characters or more, nothing else required,
     * but never a well-known password or the email itself.
     */
    protected function newPasswordRules(): array
    {
        return ['required', 'string', Password::min(8), 'max:72', new NotCommonPassword, 'different:email'];
    }

    protected function codeRules(): array
    {
        return ['required', 'digits:6'];
    }

    protected function honeypotRules(): array
    {
        return [self::HONEYPOT => ['prohibited']];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'password' => 'كلمة المرور',
            'whatsapp' => 'رقم واتساب',
            'account_type' => 'نوع الحساب',
            'code' => 'الرمز',
        ];
    }

    public function messages(): array
    {
        return [
            'email.email' => 'اكتب بريداً إلكترونياً صحيحاً، مثل name@example.com.',
            'password.different' => 'لا تجعل كلمة المرور مثل بريدك الإلكتروني.',
            'code.digits' => 'الرمز من 6 أرقام.',
            'whatsapp.required' => 'رقم واتساب مطلوب لصاحب المطعم وأخصائي التغذية، لنتواصل معك قبل تفعيل حسابك.',
            'whatsapp.regex' => 'اكتب الرقم مع رمز الدولة، مثل 963912345678+ أو 0912345678.',
            'whatsapp.unique' => 'هذا الرقم مسجّل في حساب آخر.',
            self::HONEYPOT.'.prohibited' => 'تعذّر إرسال النموذج. حدّث الصفحة وحاول مرة أخرى.',
        ];
    }
}
