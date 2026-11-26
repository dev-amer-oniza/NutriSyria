<?php

namespace App\Data\Auth;

/**
 * A validated sign-up, passed from RegisterRequest to RegistrationService. The site signs up
 * plain users only (the owner's decision): restaurants, brands and nutritionists are made
 * from the panel, by giving a signed-up account its role.
 */
final readonly class RegistrationData
{
    public function __construct(
        public string $name,
        public string $email,
        #[\SensitiveParameter] public string $password,
    ) {}
}
