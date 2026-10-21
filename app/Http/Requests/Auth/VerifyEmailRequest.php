<?php

namespace App\Http\Requests\Auth;

class VerifyEmailRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return ['code' => $this->codeRules()];
    }
}
