<?php

namespace App\Enums;

/** The account set-up, in order. The value is the URL segment. */
enum OnboardingStep: string
{
    case Body = 'body';
    case Activity = 'activity';
    case Health = 'health';

    public function number(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function title(): string
    {
        return match ($this) {
            self::Body => 'عنّي',
            self::Activity => 'نشاطي وهدفي',
            self::Health => 'صحتي',
        };
    }

    public function next(): ?self
    {
        return self::cases()[$this->number()] ?? null;
    }

    public function previous(): ?self
    {
        return self::cases()[$this->number() - 2] ?? null;
    }
}
