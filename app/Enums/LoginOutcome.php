<?php

namespace App\Enums;

enum LoginOutcome
{
    case Authenticated;
    case InvalidCredentials;
    /** Right password, but the email is not confirmed yet: a code was (re)sent. */
    case EmailUnverified;
}
