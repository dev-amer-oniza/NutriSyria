<?php

namespace App\Services\Usda;

/**
 * Pure: narrows and ranks what the USDA search returned, with the same rules as the matching
 * tool (tools/usda_to_template.py: words/stem, FORM_RULES, fit_form, EXCLUDE, score_description),
 * so a search on the page and a match from the wanted-foods list judge a description alike.
 *
 *  - every word typed must be in the description (singular and plural count as one);
 *  - a form, when chosen, must fit: "boiled" keeps "…, cooked, boiled" and drops raw, canned
 *    and frozen items, which are only kept when asked for by name;
 *  - descriptions outside a Syrian kitchen (baby food, fast food, restaurants…) never show;
 *  - the admin's own "exclude" words drop a description that has one of them;
 *  - the rest is ranked: the name at the head first, "without salt" before "with salt",
 *    and the shorter, more generic description first.
 *
 * Every dropped food keeps the reason, in Arabic, for "اعرض المستبعد".
 */
final class FdcSearchFilter
{
    /** Never part of a Syrian kitchen: the tool's EXCLUDE list. */
    public const OUTSIDE = ['babyfood', 'fast foods', 'restaurant', 'school lunch', 'infant', 'formula'];

    /** The forms one can ask for, in the order of the list on the page. */
    public const FORMS = [
        'raw' => 'نيء', 'cooked' => 'مطبوخ (أي طريقة)', 'boiled' => 'مسلوق', 'fried' => 'مقلي',
        'roasted' => 'محمّص', 'baked' => 'مخبوز', 'grilled' => 'مشوي', 'steamed' => 'مطبوخ بالبخار',
        'dried' => 'مجفف', 'canned' => 'معلّب', 'frozen' => 'مجمّد', 'pickled' => 'مخلل',
    ];

    /** First match wins: specific cooking methods before generic ones (the tool's FORM_RULES). */
    private const FORM_RULES = [
        'boiled' => '/\b(hard-?boiled|boiled|poached)\b/i',
        'fried' => '/\b(fried|pan-?fried|deep-?fried)\b/i',
        'roasted' => '/\broasted\b/i',
        'baked' => '/\bbaked\b/i',
        'grilled' => '/\b(grilled|broiled)\b/i',
        'steamed' => '/\bsteamed\b/i',
        'canned' => '/\bcanned\b/i',
        'frozen' => '/\bfrozen\b/i',
        'pickled' => '/\bpickled\b/i',
        'strained' => '/\bstrained\b/i',
        'dried' => '/\b(dried|dehydrated)\b/i',
        'cooked' => '/\bcooked\b/i',
        'raw' => '/\braw\b/i',
        'dry' => '/\bdry\b/i',
    ];

    /** A state that changes the numbers: never taken for another form unless asked for. */
    private const STATE_RULES = [
        'frozen' => '/\bfrozen\b/i',
        'canned' => '/\bcanned\b/i',
        'dried' => '/\b(dried|dehydrated|freeze-dried)\b/i',
        'pickled' => '/\bpickled\b/i',
        'strained' => '/\bstrained\b/i',
    ];

    private const COOKED_FORMS = ['cooked', 'boiled', 'steamed', 'baked', 'grilled', 'roasted'];

    private const FORM_NAMES = self::FORMS + ['strained' => 'مصفّى', 'dry' => 'جاف'];

    /**
     * @param  list<array{fdcId:int, description:string, dataType:string}&array<string,mixed>>  $foods
     * @param  list<string>  $exclude  the admin's words, each a word or a phrase
     * @param  list<string>  $sources  FDC data types to keep ("Foundation", "SR Legacy")
     * @return array{shown: list<array<string,mixed>>, dropped: list<array{food: array<string,mixed>, reason: string}>}
     */
    public function apply(array $foods, string $query, ?string $form = null, array $exclude = [], array $sources = []): array
    {
        $termWords = self::words($query);
        $segments = count(explode(',', $query));
        $form = isset(self::FORMS[$form ?? '']) ? $form : null;
        $exclude = array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim((string) $w)), $exclude)));

        $shown = [];
        $dropped = [];
        foreach ($foods as $food) {
            $desc = $food['description'];
            $reason = $this->reasonToDrop($desc, $food['dataType'], $termWords, $form, $exclude, $sources);
            if ($reason !== null) {
                $dropped[] = ['food' => $food, 'reason' => $reason];

                continue;
            }
            $shown[] = $food + ['score' => $this->score($desc, $termWords, $segments)];
        }

        usort($shown, fn (array $a, array $b) => [$b['score'], $a['dataType'] === 'Foundation' ? 0 : 1, $a['fdcId']]
            <=> [$a['score'], $b['dataType'] === 'Foundation' ? 0 : 1, $b['fdcId']]);

        return ['shown' => $shown, 'dropped' => $dropped];
    }

    /** "a;b, c" → ["a", "b", "c"]: what the admin wrote in "استبعد". */
    public static function excludeList(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;،,]+/u', $text) ?: [])));
    }

    /** @param  list<string>  $termWords */
    private function reasonToDrop(string $desc, string $dataType, array $termWords, ?string $form, array $exclude, array $sources): ?string
    {
        $low = mb_strtolower($desc);

        if ($sources !== [] && ! in_array($dataType, $sources, true)) {
            return 'من مصدر غير مختار ('.$dataType.')';
        }
        foreach (self::OUTSIDE as $word) {
            if (str_contains($low, $word)) {
                return 'خارج مطبخنا («'.$word.'»)';
            }
        }

        $have = array_flip(self::words($desc));
        $missing = array_values(array_filter($termWords, fn (string $w) => ! isset($have[$w])));
        if ($missing !== []) {
            return 'ينقصها: '.implode('، ', array_unique($missing));
        }

        foreach ($exclude as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $low)) {
                return 'فيها «'.$word.'» (استبعدتها)';
            }
        }

        if ($form !== null) {
            $state = self::firstMatch(self::STATE_RULES, $desc);
            if ($state !== null && $state !== $form) {
                return 'الشكل: '.self::FORM_NAMES[$state];
            }
            $guess = self::firstMatch(self::FORM_RULES, $desc);
            if (! $this->fits($form, $guess)) {
                return $guess === null ? 'الشكل غير مذكور' : 'الشكل: '.self::FORM_NAMES[$guess];
            }
        }

        return null;
    }

    /** The tool's fit_form, without its note. */
    private function fits(string $target, ?string $guess): bool
    {
        return match ($target) {
            'raw' => in_array($guess, ['raw', 'dry', null], true),
            'dried' => in_array($guess, ['dried', 'dry', null], true),
            'cooked' => in_array($guess, self::COOKED_FORMS, true),
            default => $guess === $target,
        };
    }

    /**
     * The tool's score_description for one term: the name at the head scores highest, then as
     * an alias in brackets, then in the first two parts, then as the first words.
     *
     * @param  list<string>  $termWords
     */
    private function score(string $desc, array $termWords, int $segments): float
    {
        $low = mb_strtolower($desc);
        $clean = preg_replace('/\([^)]*\)/', '', $desc) ?? $desc;
        $parts = explode(',', $clean);
        $head = self::words($parts[0]);
        $lead = self::words(implode(' ', array_slice($parts, 0, 2)));
        preg_match_all('/\(([^)]*)\)/', $desc, $m);
        $paren = array_flip(self::words(implode(' ', $m[1])));
        $term = array_values(array_unique($termWords));

        $score = match (true) {
            $term === [] => 0,
            $segments > 2 && self::sameSet(self::words(implode(',', array_slice($parts, 0, $segments))), $term) => 58,
            self::sameSet($head, $term) => 60,
            array_diff($term, array_keys($paren)) === [] => 50,
            self::sameSet($lead, $term) => 55,
            array_slice($head, 0, count($termWords)) === $termWords => 30,
            default => 10,
        };

        if (str_contains($low, 'without salt') || str_contains($low, 'unsalted')) {
            $score += 8;
        } elseif (str_contains($low, 'with salt') || str_contains($low, 'salt added')) {
            $score -= 25;
        }

        return round($score - mb_strlen($desc) * 0.15, 1);
    }

    /** The tool's words(): lower-case letters only, each stemmed so a plural meets its singular. */
    public static function words(string $text): array
    {
        preg_match_all('/[a-z]+/', strtolower($text), $m);

        return array_map(self::stem(...), $m[0]);
    }

    private static function stem(string $w): string
    {
        if (str_ends_with($w, 'oes') && strlen($w) > 4) {
            return substr($w, 0, -2);
        }
        if (str_ends_with($w, 'ss') || strlen($w) <= 3) {
            return $w;
        }

        return str_ends_with($w, 's') ? substr($w, 0, -1) : $w;
    }

    private static function sameSet(array $a, array $b): bool
    {
        return self::sorted(array_unique($a)) === self::sorted(array_unique($b));
    }

    private static function sorted(array $a): array
    {
        $a = array_values($a);
        sort($a);

        return $a;
    }

    /** @param  array<string, string>  $rules */
    private static function firstMatch(array $rules, string $desc): ?string
    {
        foreach ($rules as $name => $rx) {
            if (preg_match($rx, $desc)) {
                return $name;
            }
        }

        return null;
    }
}
