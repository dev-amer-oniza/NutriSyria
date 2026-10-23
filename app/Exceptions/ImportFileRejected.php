<?php

namespace App\Exceptions;

use RuntimeException;

/** An uploaded import file that cannot be read as a bundle sheet; the message says why, in Arabic. */
class ImportFileRejected extends RuntimeException {}
