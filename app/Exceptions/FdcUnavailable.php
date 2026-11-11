<?php

namespace App\Exceptions;

use RuntimeException;

/** USDA's API could not answer; the message is written for the admin, in Arabic. */
class FdcUnavailable extends RuntimeException {}
