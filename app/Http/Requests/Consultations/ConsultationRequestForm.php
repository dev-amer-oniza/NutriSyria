<?php

namespace App\Http\Requests\Consultations;

use App\Http\Requests\Auth\AuthFormRequest;

/**
 * Asking a nutritionist for a consultation: a short reason, a WhatsApp number (saved on the
 * account for next time), and consent to share the health record once accepted.
 */
class ConsultationRequestForm extends AuthFormRequest
{
    public const REASON_MIN = 10;

    public const REASON_MAX = 500;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'reason' => preg_replace('/[ \t]+/u', ' ', trim((string) $this->input('reason'))),
            'whatsapp' => self::normalizeWhatsapp($this->input('whatsapp')),
        ]);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX],
            'whatsapp' => ['required', ...$this->whatsappRules($this->user())],
            'consent' => ['accepted'],
            ...$this->honeypotRules(),
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['reason' => 'سبب الاستشارة', 'consent' => 'الموافقة'];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'اكتب باختصار لماذا تريد الاستشارة.',
            'reason.min' => 'اكتب سطراً على الأقل، ليفهم الأخصائي ما تحتاجه.',
            'reason.max' => 'السبب أطول من '.self::REASON_MAX.' حرف. اختصره، وتكملان على واتساب.',
            'whatsapp.required' => 'رقم واتساب مطلوب، ليتواصل معك الأخصائي بعد القبول.',
            'consent.accepted' => 'الموافقة مطلوبة: الأخصائي يحتاج سجلك الصحي ليحدد معاييرك.',
        ] + parent::messages();
    }
}
