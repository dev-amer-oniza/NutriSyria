<?php

namespace App\Data\Auth;

/** What a sign-in provider (Google) tells us about the person, already cleaned. */
final readonly class SocialProfile
{
    public function __construct(
        public string $providerId,
        public string $email,
        public string $name,
        public bool $emailVerified,
    ) {}

    /** @return array<string, mixed> for keeping in the session between two requests */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            providerId: (string) $data['providerId'],
            email: (string) $data['email'],
            name: (string) $data['name'],
            emailVerified: (bool) $data['emailVerified'],
        );
    }
}
