<?php

namespace App\Http\Requests\Auth;

use App\Data\Auth\RegistrationData;
use App\Models\User;
use Illuminate\Validation\Rule;

/** Sign-up on the site: plain users only; a restaurant, brand or nutritionist is set up from the panel. */
class RegisterRequest extends AuthFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\p{L}\p{M}][\p{L}\p{M} \'.-]*$/u'],
            'email' => [...$this->emailRules(), Rule::unique(User::class, 'email')],
            'password' => $this->newPasswordRules(),
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
        );
    }
}
