<?php

namespace App\Enums;

enum Goal: string
{
    case Lose = 'lose';
    case Maintain = 'maintain';
    case Gain = 'gain';

    public function label(): string
    {
        return match ($this) {
            self::Lose => 'إنقاص الوزن',
            self::Maintain => 'المحافظة على وزني',
            self::Gain => 'زيادة الوزن',
        };
    }
}
