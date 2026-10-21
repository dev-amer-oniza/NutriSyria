<?php

namespace App\Exceptions;

use RuntimeException;

/** The person's nutritionist set this criterion; only the nutritionist changes it. */
class CriterionLocked extends RuntimeException
{
    public function userMessage(): string
    {
        return 'حدّد أخصائيّك هذا المعيار. تواصل معه إن أردت تغييره.';
    }
}
