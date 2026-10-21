<?php

namespace App\Http\Requests\Auth;

/** The one field a new brand owner or nutritionist coming from Google still owes. */
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
            ...$this->honeypotRules(),
        ];
    }
}
