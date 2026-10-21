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

    public function rules(): array
    {
        return [
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\p{M}][\p{L}\p{M} \'.-]*$/u'],
            'email' => [...$this->emailRules(), Rule::unique(User::class, 'email')],
            'password' => $this->newPasswordRules(),
            'whatsapp' => [
                Rule::requiredIf(fn () => AccountType::tryFrom((string) $this->input('account_type'))?->needsWhatsapp() ?? false),
                'nullable', ...$this->whatsappRules(),
            ],
            ...$this->honeypotRules(),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'name.regex' => 'اكتب الاسم بالحروف فقط.',
            'email.unique' => 'هذا البريد مسجّل لدينا. سجّل الدخول، أو استعد كلمة المرور إن نسيتها.',
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
