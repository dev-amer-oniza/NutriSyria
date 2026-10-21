<?php

namespace App\Http\Requests\Profile;

use App\Services\Nutrition\Criteria\PersonalCriteria;

/** A person's own value for one criterion, within the sensible range set in config. */
class CriterionRequest extends ProfileFormRequest
{
    protected array $numbers = ['value'];

    public function rules(): array
    {
        [$min, $max] = PersonalCriteria::range($this->route('criterion'));

        return ['value' => ['required', 'numeric', "between:$min,$max"]];
    }

    public function messages(): array
    {
        [$min, $max] = PersonalCriteria::range($this->route('criterion'));

        return ['value.between' => "اكتب رقماً بين $min و$max."];
    }
}
