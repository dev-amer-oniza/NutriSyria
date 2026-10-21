<?php

namespace App\Http\Requests\Profile;

use App\Enums\ActivityLevel;
use App\Enums\Goal;
use Illuminate\Validation\Rule;

class ActivityStepRequest extends ProfileFormRequest
{
    public function rules(): array
    {
        return [
            'activity_level' => ['required', Rule::enum(ActivityLevel::class)],
            'goal' => ['required', Rule::enum(Goal::class)],
        ];
    }

    public function attributes(): array
    {
        return ['activity_level' => 'مستوى النشاط', 'goal' => 'الهدف'];
    }
}
