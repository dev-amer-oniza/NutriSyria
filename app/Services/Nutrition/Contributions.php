<?php

namespace App\Services\Nutrition;

/**
 * "من أين؟" (step 4 of the medical plan): the ingredients that give a meal its fat, salt or
 * fibre, as the site shows them. Pure. The largest three, and what gives less than OTHERS_BELOW
 * percent together under "مكوّنات أخرى". A restaurant's recipe stays private: its ingredients
 * by rank only, never grams or shares (the owner's decision on recipe secrecy).
 */
final class Contributions
{
    public const SHOWN = 3;

    /** An ingredient giving less than this share (%) joins "مكوّنات أخرى". */
    public const OTHERS_BELOW = 5;

    public const OTHERS = 'مكوّنات أخرى';

    /**
     * @param  array<string, list<array{label: string, amount: float, after: bool}>>  $byNutrient  largest first
     * @return array<string, list<array{label: string, amount: ?float, pct: ?int}>>
     */
    public static function rows(array $byNutrient, float $factor, bool $ranksOnly): array
    {
        $out = [];
        foreach ($byNutrient as $code => $lines) {
            $total = array_sum(array_column($lines, 'amount'));
            if ($total <= 0) {
                continue;
            }
            $rows = [];
            $rest = 0.0;
            foreach ($lines as $line) {
                $pct = $line['amount'] / $total * 100;
                if (count($rows) < self::SHOWN && $pct >= self::OTHERS_BELOW) {
                    $rows[] = ['label' => self::label($line), 'amount' => $line['amount'] * $factor, 'pct' => (int) round($pct)];
                } else {
                    $rest += $line['amount'];
                }
            }
            if ($ranksOnly) {
                $rows = array_map(fn (array $row) => ['label' => $row['label'], 'amount' => null, 'pct' => null], $rows);
            } elseif ($rest > 0) {
                $rows[] = ['label' => self::OTHERS, 'amount' => $rest * $factor, 'pct' => (int) round($rest / $total * 100)];
            }
            $out[$code] = $rows;
        }

        return $out;
    }

    /** "زيت الزيتون على الوجه": what is added after cooking is told apart (decision 4a). */
    private static function label(array $line): string
    {
        return $line['label'].($line['after'] ? ' على الوجه' : '');
    }
}
