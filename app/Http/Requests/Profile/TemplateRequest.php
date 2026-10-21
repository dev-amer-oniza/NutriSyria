<?php

namespace App\Http\Requests\Profile;

use App\Services\Nutrition\Criteria\PersonalCriteria;
use Illuminate\Validation\Rule;

/** A person picking their own diet template (only the general ones; a nutritionist picks any). */
class TemplateRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        return ['template' => ['required', 'string', Rule::in(array_keys(PersonalCriteria::selectableTemplates()))]];
    }

    public function messages(): array
    {
        return ['template.in' => 'اختر أحد القوالب المعروضة.'];
    }
}
