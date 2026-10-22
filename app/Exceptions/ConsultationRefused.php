<?php

namespace App\Exceptions;

use RuntimeException;

/** A consultation step that is not allowed now; the message is shown to the person as is. */
class ConsultationRefused extends RuntimeException
{
    /** Where the person should go next: their basic details, or their open request. */
    public const COMPLETE_PROFILE = 'complete_profile';

    public const HAS_OPEN = 'has_open';

    public function __construct(string $message, public readonly ?string $next = null)
    {
        parent::__construct($message);
    }

    public static function alreadyOpen(): self
    {
        return new self('لديك طلب أو أخصائي حالياً. أنهِ الارتباط أو ألغِ الطلب أولاً، فالأخصائي واحد في كل مرة.', self::HAS_OPEN);
    }

    public static function notAvailable(): self
    {
        return new self('هذا الأخصائي لا يستقبل طلبات الآن. اختر أخصائياً آخر.');
    }

    public static function ownProfile(): self
    {
        return new self('لا يمكنك طلب استشارة من نفسك.');
    }

    public static function profileFirst(): self
    {
        return new self('أكمل بياناتك الأساسية أولاً (العمر والجنس والهدف)، ليعرف الأخصائي من يراجعه.', self::COMPLETE_PROFILE);
    }

    public static function notPending(): self
    {
        return new self('لم يعد هذا الطلب بانتظار الرد.');
    }

    public static function notAccepted(): self
    {
        return new self('لا يوجد ارتباط قائم لإنهائه.');
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }
}
