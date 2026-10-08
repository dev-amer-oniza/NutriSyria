<?php

namespace App\Exceptions;

use Exception;

class CartTenantMismatchException extends Exception
{
    public function __construct(
        public readonly string $existing,
        public readonly string $incoming,
    ) {
        parent::__construct(
            "Cart already contains items from tenant [{$existing}]. " .
            "Cannot add items from [{$incoming}]."
        );
    }
}
