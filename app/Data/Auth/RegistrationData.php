<?php

namespace App\Data\Auth;

use App\Enums\AccountType;

/** A validated sign-up, passed from RegisterRequest to RegistrationService. */
final readonly class RegistrationData
{
    public function __construct(
        public string $name,
        public string $email,
        #[\SensitiveParameter] public string $password,
        public AccountType $accountType,
        public ?string $whatsapp,
    ) {}
}
