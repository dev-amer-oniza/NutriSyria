<?php

namespace App\Http\Requests\Auth;

use App\Data\Auth\RegistrationData;
use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Validation\Rule;

class RegisterRequest extends AuthFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $type = AccountType::tryFrom((string) $this->input('account_type'));

        $this->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name'))),
            // Only brand owners and nutritionists give a number; anything else sent is dropped.
            'whatsapp' => $type?->needsWhatsapp() ? self::normalizeWhatsapp($this->input('whatsapp')) : null,
        ]);
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

    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\p{M}][\p{L}\p{M} \'.-]*$/u'],
            'email' => [...$this->emailRules(), Rule::unique(User::class, 'email')],
            'password' => $this->newPasswordRules(),
            'whatsapp' => [
                Rule::requiredIf(fn () => AccountType::tryFrom((string) $this->input('account_type'))?->needsWhatsapp() ?? false),
                'nullable', 'string', 'regex:/^\+[1-9]\d{7,14}$/', Rule::unique(User::class, 'phone'),
            ],
            ...$this->honeypotRules(),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'name.regex' => 'اكتب الاسم بالحروف فقط.',
            'email.unique' => 'هذا البريد مسجّل لدينا. سجّل الدخول، أو استعد كلمة المرور إن نسيتها.',
            'whatsapp.required' => 'رقم واتساب مطلوب لصاحب المطعم وأخصائي التغذية، لنتواصل معك قبل تفعيل حسابك.',
            'whatsapp.regex' => 'اكتب الرقم مع رمز الدولة، مثل 963912345678+ أو 0912345678.',
            'whatsapp.unique' => 'هذا الرقم مسجّل في حساب آخر.',
        ];
    }

    public function toData(): RegistrationData
    {
        return new RegistrationData(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
            accountType: AccountType::from($this->validated('account_type')),
            whatsapp: $this->validated('whatsapp'),
        );
    }
}
