<?php

namespace App\Http\Requests\Restaurants;

use App\Enums\BrandKind;
use Illuminate\Foundation\Http\FormRequest;

/** The restaurants page's search, kind and "delivers" filters; odd input is trimmed or dropped, never refused. */
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
            'kind' => BrandKind::tryFrom((string) $this->query('kind'))?->value,
        ]);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH],
            'delivery' => ['boolean'],
            'kind' => ['nullable', 'string'],
        ];
    }

    public function search(): ?string
    {
        return $this->validated('q');
    }

    public function kind(): ?BrandKind
    {
        return BrandKind::tryFrom((string) $this->validated('kind'));
    }

    public function deliveryOnly(): bool
    {
        return (bool) $this->validated('delivery');
    }
}
