<?php

namespace App\Enums;

enum ConsultationMode: string
{
    case Online = 'online';
    case Clinic = 'clinic';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'أونلاين',
            self::Clinic => 'في العيادة',
            self::Both => 'أونلاين وفي العيادة',
        };
    }

    public function needsAddress(): bool
    {
        return $this !== self::Online;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_column(array_map(fn (self $m) => ['v' => $m->value, 'l' => $m->label()], self::cases()), 'l', 'v');
    }
}
