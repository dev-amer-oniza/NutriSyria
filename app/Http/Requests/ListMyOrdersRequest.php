<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListMyOrdersRequest extends FormRequest
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
            'status' => ['sometimes', 'string', 'in:pending,confirmed,ready,dispatched,delivered,cancelled'],
            'type' => ['sometimes', 'string', 'in:delivery,pickup'],
            //'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            //'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
    public function filters(): array
    {
        return $this->only(['status', 'type']);
    }
}
