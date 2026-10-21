<?php

namespace App\Http\Requests\Profile;

use App\Http\Requests\Auth\AuthFormRequest;
use Illuminate\Foundation\Http\FormRequest;

/** Shared cleaning for the profile forms: numbers typed with Arabic digits or a comma. */
abstract class ProfileFormRequest extends FormRequest
{
    /** Fields holding a number; "٧٥٫٥" and "75,5" both become "75.5". */
    protected array $numbers = [];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach ($this->numbers as $field) {
            if ($this->filled($field)) {
                $clean[$field] = self::number($this->input($field));
            }
        }
        $this->merge($clean);
    }

    public static function number(mixed $value): string
    {
        $value = AuthFormRequest::latinDigits(trim((string) $value));

        return str_replace(['٫', ',', '،'], '.', preg_replace('/\s+/u', '', $value));
    }
}
