<?php

namespace App\Http\Requests\Daily;

use App\Enums\MealType;
use App\Http\Requests\Auth\AuthFormRequest;
use Illuminate\Validation\Rule;

/** "ما لقيتها؟": the dish's name and what is in it, so we add one made the same way. */
class FoodRequestForm extends AuthFormRequest
{
    public const NAME_MAX = 120;

    public const INGREDIENTS_MIN = 5;

    public const INGREDIENTS_MAX = 500;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'food_name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('food_name'))),
            'ingredients' => preg_replace('/[ \t]+/u', ' ', trim((string) $this->input('ingredients'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'food_name' => ['required', 'string', 'min:2', 'max:'.self::NAME_MAX],
            'ingredients' => ['required', 'string', 'min:'.self::INGREDIENTS_MIN, 'max:'.self::INGREDIENTS_MAX],
            'meal_type' => ['required', Rule::enum(MealType::class)],
            ...$this->honeypotRules(),
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['food_name' => 'اسم الأكلة', 'ingredients' => 'مكوّناتها'];
    }

    public function messages(): array
    {
        return [
            'food_name.required' => 'اكتب اسم الأكلة.',
            'food_name.min' => 'اكتب اسم الأكلة.',
            'food_name.max' => 'الاسم أطول من '.self::NAME_MAX.' حرف.',
            'ingredients.required' => 'اكتبلنا شو فيها تقريباً، لنضيف أكلة بنفس المكوّنات.',
            'ingredients.min' => 'اكتبلنا شو فيها تقريباً، لنضيف أكلة بنفس المكوّنات.',
            'ingredients.max' => 'المكوّنات أطول من '.self::INGREDIENTS_MAX.' حرف. اختصرها.',
            'meal_type.required' => 'اختار أي وجبة كانت.',
        ] + parent::messages();
    }
}
