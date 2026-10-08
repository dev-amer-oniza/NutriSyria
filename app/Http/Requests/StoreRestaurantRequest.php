<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;


class StoreRestaurantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'restaurant_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('restaurants','name'),
            ],

            'restaurant_phone' => [
                'required',
                'string',
                'regex:/^\+963\s?9\d{2}\s?\d{3}\s?\d{3}$/',
            ],

            'address' => 'required|string|max:255',

            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            'categories.*' => 'exists:categories,id'

        ];
    }

    public function messages(): array
    {
        return [
            'restaurant_name.unique' => 'this restaurant exisit',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => ' Data verification error',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
