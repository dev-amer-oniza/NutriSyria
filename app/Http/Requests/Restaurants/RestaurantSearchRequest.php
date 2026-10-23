<?php

namespace App\Http\Requests\Restaurants;

use Illuminate\Foundation\Http\FormRequest;

/** The restaurants page's search and "delivers" filter; odd input is trimmed, never refused. */
class RestaurantSearchRequest extends FormRequest
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
            'delivery' => $this->boolean('delivery'),
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH],
            'delivery' => ['boolean'],
        ];
    }

    public function search(): ?string
    {
        return $this->validated('q');
    }

    public function deliveryOnly(): bool
    {
        return (bool) $this->validated('delivery');
    }
}
