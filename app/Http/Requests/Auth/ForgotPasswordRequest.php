<?php

namespace App\Http\Requests\Auth;

class ForgotPasswordRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', 'email'],
            ...$this->honeypotRules(),
        ];
    }
}
