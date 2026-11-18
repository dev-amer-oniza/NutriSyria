<?php

namespace App\Http\Requests\Profile;

use App\Enums\Allergen;
use Illuminate\Validation\Rule;

/** The allergies step. Anything chosen needs the person's consent to keep it. */
class HealthStepRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        $chosen = filled($this->input('allergies'));

        return [
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['distinct', Rule::enum(Allergen::class)],
            'consent' => $chosen ? ['accepted'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return ['consent.accepted' => 'وافق على حفظ معلوماتك الصحية، أو أزل اختياراتك.'];
    }

    /** @return list<Allergen> */
    public function allergens(): array
    {
        return array_map(Allergen::from(...), $this->validated('allergies') ?? []);
    }
}
