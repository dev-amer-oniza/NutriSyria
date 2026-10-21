<?php

namespace App\Http\Requests\Profile;

use App\Enums\Allergen;
use App\Enums\HealthCondition;
use Closure;
use Illuminate\Validation\Rule;

/** The optional step. Anything chosen needs the person's consent to keep it. */
class HealthStepRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        $chosen = filled($this->input('conditions')) || filled($this->input('allergies'));
        $sex = $this->user()->profile?->sex;

        return [
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['distinct', Rule::enum(HealthCondition::class),
                function (string $attribute, mixed $value, Closure $fail) use ($sex) {
                    if (! HealthCondition::from($value)->appliesTo($sex)) {
                        $fail('هذا الخيار للنساء فقط.');
                    }
                }],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['distinct', Rule::enum(Allergen::class)],
            'consent' => $chosen ? ['accepted'] : ['nullable'],
        ];
    }

    public function messages(): array
    {
        return ['consent.accepted' => 'وافق على حفظ معلوماتك الصحية، أو أزل اختياراتك.'];
    }

    /** @return list<HealthCondition> */
    public function conditions(): array
    {
        return array_map(HealthCondition::from(...), $this->validated('conditions') ?? []);
    }

    /** @return list<Allergen> */
    public function allergens(): array
    {
        return array_map(Allergen::from(...), $this->validated('allergies') ?? []);
    }
}
