<?php

namespace App\Http\Requests\Consultations;

use App\Enums\NutritionistSpecialty;
use App\Enums\SyrianGovernorate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The directory's filters; an unknown value is simply ignored (a shared link must not break). */
class NutritionistSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'specialty' => NutritionistSpecialty::tryFrom((string) $this->query('specialty'))?->value,
            'city' => SyrianGovernorate::tryFrom((string) $this->query('city'))?->value,
        ]);
    }

    public function rules(): array
    {
        return [
            'specialty' => ['nullable', Rule::enum(NutritionistSpecialty::class)],
            'city' => ['nullable', Rule::enum(SyrianGovernorate::class)],
        ];
    }

    public function specialty(): ?NutritionistSpecialty
    {
        return NutritionistSpecialty::tryFrom((string) $this->validated('specialty'));
    }

    public function city(): ?SyrianGovernorate
    {
        return SyrianGovernorate::tryFrom((string) $this->validated('city'));
    }
}
