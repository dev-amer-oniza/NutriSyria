<?php

namespace App\Exceptions;

use RuntimeException;

/** The mail server refused or could not be reached while sending a code. */
class CodeDeliveryFailed extends RuntimeException
{
    public function userMessage(): string
    {
        return 'تعذّر إرسال الرمز إلى بريدك الآن. حاول بعد قليل.';
    }
}
