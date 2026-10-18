<?php

namespace App\Support;

use App\Services\Import\ArabicNormalizer;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arabic search that ignores spelling variants: "ارز" finds "أرز", "جبنه" finds "جبنة".
 *
 * The search term goes through ArabicNormalizer; the column gets the same folding in SQL
 * (nested REPLACE, so it works on MySQL and SQLite with no extra column to keep in sync).
 */
final class ArabicSearch
{
    /** Folded to the same letter, as ArabicNormalizer does. */
    private const FOLD = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي',
        'ة' => 'ه',
        // Diacritics and tatweel are dropped.
        'ً' => '', 'ٌ' => '', 'ٍ' => '', 'َ' => '', 'ُ' => '', 'ِ' => '', 'ّ' => '', 'ْ' => '', 'ٰ' => '', 'ـ' => '',
    ];

    /** Rows where any of the columns contains the term, spelling variants aside. */
    public static function constrain(Builder $query, array $columns, string $search): Builder
    {
        $term = ArabicNormalizer::normalize($search);

        if ($term === '') {
            return $query;
        }

        // "!" escapes the wildcards: a backslash means different things to MySQL and SQLite.
        $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $query) use ($columns, $pattern, $grammar) {
            foreach ($columns as $column) {
                $query->orWhereRaw(self::fold($grammar->wrap($column)) . " like ? escape '!'", [$pattern]);
            }
        });
    }

    /** For a table column: ->searchable(query: ArabicSearch::column('name_ar')). */
    public static function column(string ...$columns): Closure
    {
        return fn (Builder $query, string $search): Builder => self::constrain($query, $columns, $search);
    }

    private static function fold(string $sql): string
    {
        $sql = "lower({$sql})";

        foreach (self::FOLD as $from => $to) {
            $sql = "replace({$sql}, '{$from}', '{$to}')";
        }

        return $sql;
    }
}
