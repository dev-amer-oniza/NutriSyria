<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendOtpRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            "email" => ["required", "email", "exists:users,email"],
        ];
    }
    public function messages(): array
    {
        return [
            'email.exists' => 'An unexpected error occurred.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => ' OTP_SEND_FAILED',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
