<?php

namespace App\Http\Requests\Profile;

use App\Enums\Sex;
use Illuminate\Validation\Rule;

class BodyStepRequest extends ProfileFormRequest
{
    protected array $numbers = ['birth_year', 'height_cm', 'weight_kg'];

    public function rules(): array
    {
        $year = (int) now()->year;

        return [
            'sex' => ['required', Rule::enum(Sex::class)],
            'birth_year' => ['required', 'integer', 'between:'.($year - 100).','.($year - 10)],
            'height_cm' => ['required', 'numeric', 'between:120,230'],
            'weight_kg' => ['required', 'numeric', 'between:30,300'],
        ];
    }

    public function attributes(): array
    {
        return ['sex' => 'الجنس', 'birth_year' => 'سنة الميلاد', 'height_cm' => 'الطول', 'weight_kg' => 'الوزن'];
    }

    public function messages(): array
    {
        return [
            'birth_year.between' => 'اكتب سنة ميلادك بالأرقام، مثل 1994.',
            'height_cm.between' => 'اكتب طولك بالسنتيمتر، بين 120 و230، مثل 172.',
            'weight_kg.between' => 'اكتب وزنك بالكيلوغرام، بين 30 و300، مثل 75.5.',
        ];
    }
}
