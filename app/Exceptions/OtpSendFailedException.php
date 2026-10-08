<?php

namespace App\Exceptions;

use Exception;

class OtpSendFailedException extends Exception
{
    protected $message = 'OTP_SEND_FAILED';
}
