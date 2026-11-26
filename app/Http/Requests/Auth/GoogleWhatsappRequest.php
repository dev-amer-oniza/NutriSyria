<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Enums\BrandKind;
use App\Http\Controllers\Site\Auth\GoogleAuthController;
use Illuminate\Validation\Rule;

/**
 * What a new brand owner or nutritionist coming from Google still owes: a WhatsApp number,
 * and for a brand owner whether they run a restaurant or a brand.
 */
class GoogleWhatsappRequest extends AuthFormRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['whatsapp' => self::normalizeWhatsapp($this->input('whatsapp'))]);
    }

    public function rules(): array
    {
        return [
            'whatsapp' => ['required', ...$this->whatsappRules()],
            'brand_kind' => [Rule::requiredIf(fn () => $this->forBrandOwner()), 'nullable', Rule::enum(BrandKind::class)],
            ...$this->honeypotRules(),
        ];
    }

    /** The sign-up waiting in the session is a brand owner's. */
    public function forBrandOwner(): bool
    {
        return ($this->session()->get(GoogleAuthController::SESSION_PENDING)['type'] ?? null) === AccountType::BrandOwner->value;
    }

    public function messages(): array
    {
        return parent::messages() + ['brand_kind.required' => 'اختار: مطعم ولا براند؟'];
    }
}
