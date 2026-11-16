<?php

namespace App\Http\Requests\Kitchen;

use App\Data\Nutrition\RecipeDraft;
use App\Data\Nutrition\RecipeLine;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor's own version of a kitchen dish ("اطبخها على طريقتك"). Only shapes and limits here;
 * whether each ingredient is an approved record is checked by CustomRecipeAnalyzer.
 */
class CustomRecipeRequest extends FormRequest
{
    public const MAX_LINES = 30;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.record_id' => ['required', 'integer', 'min:1'],
            'lines.*.unit_id' => ['required', 'integer', 'min:1'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'lines.*.portion_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.after' => ['boolean'],
            'servings' => ['required', 'integer', 'min:1', 'max:50'],
            'weight_after' => ['nullable', 'numeric', 'min:1', 'max:50000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'أضف مكوّناً واحداً على الأقل.',
            'lines.max' => 'الوصفة تقبل حتى '.self::MAX_LINES.' مكوّناً.',
            'lines.*.record_id.required' => 'اختر المكوّن من القائمة في كل سطر.',
            'lines.*.unit_id.required' => 'اختر وحدة القياس في كل سطر.',
            'lines.*.amount.required' => 'اكتب الكمية في كل سطر.',
            'lines.*.amount.gt' => 'الكمية يجب أن تكون أكبر من صفر.',
            'lines.*.amount.max' => 'الكمية كبيرة جداً.',
            'servings.*' => 'عدد الحصص بين 1 و50.',
            'weight_after.*' => 'الوزن بعد الطبخ بين 1 و50000 غرام، أو اتركه فارغاً.',
        ];
    }

    public function draft(): RecipeDraft
    {
        $data = $this->validated();

        return new RecipeDraft(
            lines: array_map(fn (array $l) => new RecipeLine(
                recordId: (int) $l['record_id'],
                unitId: (int) $l['unit_id'],
                amount: (float) $l['amount'],
                portionId: isset($l['portion_id']) ? (int) $l['portion_id'] : null,
                addedAfterCooking: (bool) ($l['after'] ?? false),
            ), array_values($data['lines'])),
            servings: (int) $data['servings'],
            weightAfterCookingG: isset($data['weight_after']) ? (float) $data['weight_after'] : null,
        );
    }
}
