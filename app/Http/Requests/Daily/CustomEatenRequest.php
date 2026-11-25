<?php

namespace App\Http\Requests\Daily;

use App\Enums\MealType;
use App\Http\Requests\Kitchen\CustomRecipeRequest;
use Illuminate\Validation\Rule;

/**
 * "أكلتها" under "نتيجة طبختك": the recipe itself (never the numbers shown: they are worked
 * out again from approved ingredients, the owner's decision a), how much of the person's own
 * plate, and which meal of the day.
 */
class CustomEatenRequest extends CustomRecipeRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return parent::rules() + [
            'eaten' => ['required', 'numeric', 'min:0.25', 'max:4'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'eaten.*' => 'اختار قديش أكلت من صحنك.',
            'meal_type.*' => 'اختار أي وجبة كانت.',
        ];
    }

    public function eaten(): float
    {
        return (float) $this->validated('eaten');
    }

    public function mealType(): MealType
    {
        return MealType::from($this->validated('meal_type'));
    }
}
