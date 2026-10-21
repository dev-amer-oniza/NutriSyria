<?php

namespace App\Http\Requests\Auth;

use App\Http\Controllers\Site\Auth\PasswordResetController;

/** The email comes from the session (set when the code was asked for), not from the form. */
class ResetPasswordRequest extends AuthFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => (string) $this->session()->get(PasswordResetController::SESSION_EMAIL)]);

        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'code' => $this->codeRules(),
            'password' => $this->newPasswordRules(),
        ];
    }

    public function messages(): array
    {
        return parent::messages() + ['email.required' => 'انتهت الجلسة. اطلب رمزاً جديداً.'];
    }
}
