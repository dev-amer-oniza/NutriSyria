<?php

namespace App\Http\Requests\Daily;

use Illuminate\Foundation\Http\FormRequest;

/** "سجّل وزنك": kilograms, Arabic digits and a comma accepted ("٧١٫٥", "71,5"). */
class WeightRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $kg = strtr(trim((string) $this->input('weight_kg')), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', ',' => '.',
        ]);
        $this->merge(['weight_kg' => $kg]);
    }

    public function rules(): array
    {
        $range = config('site.daily.weight_kg');

        return ['weight_kg' => ['required', 'numeric', "between:{$range['min']},{$range['max']}"]];
    }

    public function messages(): array
    {
        $range = config('site.daily.weight_kg');

        return [
            'weight_kg.required' => 'اكتب وزنك بالكيلوغرام.',
            'weight_kg.numeric' => 'اكتب وزنك بالأرقام، مثل 71.5.',
            'weight_kg.between' => "اكتب وزنك بالكيلوغرام، بين {$range['min']} و{$range['max']}، مثل 71.5.",
        ];
    }

    public function kilograms(): float
    {
        return (float) $this->validated('weight_kg');
    }
}
