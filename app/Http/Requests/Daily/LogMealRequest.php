<?php

namespace App\Http\Requests\Daily;

use App\Enums\MealType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "أكلتها": how much of the meal, and at which meal of the day. The meal itself is in the URL. */
class LogMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.25', 'max:4'],
            'meal_type' => ['required', Rule::enum(MealType::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'اختار قديش أكلت منها.',
            'meal_type.required' => 'اختار أي وجبة كانت.',
        ];
    }

    public function amount(): float
    {
        return (float) $this->validated('amount');
    }

    public function mealType(): MealType
    {
        return MealType::from($this->validated('meal_type'));
    }
}
