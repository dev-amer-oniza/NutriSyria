<?php

namespace App\Http\Requests\Meals;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The meals page's search and "only what suits me" filter. Odd input is trimmed or dropped
 * rather than refused, so a shared or hand-typed link always opens.
 */
class MealSearchRequest extends FormRequest
{
    public const MAX_SEARCH = 80;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $q = preg_replace('/\s+/u', ' ', trim((string) $this->query('q')));

        $this->merge([
            'q' => $q === '' ? null : mb_substr($q, 0, self::MAX_SEARCH),
            'suits' => $this->boolean('suits'),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH],
            'suits' => ['boolean'],
        ];
    }

    public function search(): ?string
    {
        return $this->validated('q');
    }

    /** Only meals that suit the person (very well or in moderation). */
    public function onlySuiting(): bool
    {
        return (bool) $this->validated('suits');
    }
}
