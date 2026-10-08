<?php

namespace App\Services\Import;

/**
 * Search key for Arabic names: same word written with different diacritics, alef forms
 * or ta-marbuta/ha should match. If your app already has a normalizer for
 * food_aliases.search_normalized, make this class call it so both agree.
 */
final class ArabicNormalizer
{
    private const DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(string $text): string
    {
        $t = strtr(trim($text), self::DIGITS);
        $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $t) ?? $t; // diacritics, tatweel
        $t = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $t);
        $t = str_replace('ى', 'ي', $t);
        $t = str_replace('ة', 'ه', $t);
        $t = mb_strtolower($t);

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }
}
