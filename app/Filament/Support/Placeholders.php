<?php

namespace App\Filament\Support;

/**
 * Small images shown where a food, brand or product has no picture yet, instead of a broken
 * image. Inline SVG data URIs: no request, no file to ship. The neutral grey reads on both the
 * light and the dark background.
 */
final class Placeholders
{
    private const ICONS = [
        // olive leaf
        'food' => '<path d="M9 23c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M9 23 17 15"/>',
        // shop front
        'brand' => '<path d="M8 13l1.5-5h13l1.5 5"/><path d="M8 13a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M9.5 15.5V24h13v-8.5"/>',
        // shopping bag
        'product' => '<path d="M10 12h12l1 12H9z"/><path d="M13 12v-1a3 3 0 0 1 6 0v1"/>',
    ];

    public static function image(string $kind): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            .'<circle cx="16" cy="16" r="16" fill="#a8a29e" fill-opacity=".16"/>'
            .'<g fill="none" stroke="#a8a29e" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
            .self::ICONS[$kind]
            .'</g></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
