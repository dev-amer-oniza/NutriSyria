<?php

namespace App\Support;

/** A number written short for people: 8.5, not 8.50; 8, not 8.0. */
final class Amount
{
    public static function short(float|int|string|null $value, int $decimals = 2): string
    {
        $text = number_format((float) $value, $decimals, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
