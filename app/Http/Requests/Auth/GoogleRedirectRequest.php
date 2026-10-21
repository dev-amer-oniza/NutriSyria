<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use Illuminate\Validation\Rule;

/** "Continue with Google": the account type chosen on the sign-up page, if any. */
class GoogleRedirectRequest extends AuthFormRequest
{
    public function rules(): array
    {
        return ['type' => ['nullable', Rule::enum(AccountType::class)]];
    }

    public function accountType(): AccountType
    {
        return AccountType::tryFrom((string) $this->validated('type')) ?? AccountType::Member;
    }
}
