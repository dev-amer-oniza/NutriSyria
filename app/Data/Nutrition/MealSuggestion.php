<?php

namespace App\Data\Nutrition;

/** A change tried on a meal and judged again (MealSuggestions). */
final readonly class MealSuggestion
{
    /**
     * @param  list<array{ingredient: string, after: bool, keep: float}>  $changes  one or two cuts; keep 0 = without it
     */
    public function __construct(
        public array $changes,
        /** The level the changed meal gets (always higher). */
        public int $level,
        /** The nutrient the change is about, and its new amount in the portion (null: a restaurant's, never shown). */
        public ?string $nutrient = null,
        public ?float $amount = null,
        /** Grams of food the change takes off the plate, to offer the smallest change first. */
        public float $cutGrams = 0.0,
    ) {}

    /** Only what goes on top is left out: the easiest change at home, the only one at a restaurant. */
    public function onTopOnly(): bool
    {
        return array_filter($this->changes, fn (array $c) => ! $c['after']) === [];
    }

    /** "بلا زيت زيتون على الوجه، وطحينة بثلثي الكمية", or "اطلبها بلا سمنة على الوجه". */
    public function text(bool $restaurant): string
    {
        $parts = array_map(fn (array $c) => self::describe($c['ingredient'], $c['after'], $c['keep']), $this->changes);

        return ($restaurant ? 'اطلبها ' : '').implode('، و', $parts);
    }

    /** The cut of one ingredient, in words. */
    public static function describe(string $ingredient, bool $after, float $keep): string
    {
        $name = $ingredient.($after ? ' على الوجه' : '');

        return $keep <= 0 ? 'بلا '.$name : $name.' '.self::fraction($keep);
    }

    /** What the page sends to "اطبخها على طريقتك" to apply it: c[n][i|a|k]. */
    public function change(): array
    {
        return ['c' => array_map(fn (array $c) => ['i' => $c['ingredient'], 'a' => (int) $c['after'], 'k' => round($c['keep'], 4)], $this->changes)];
    }

    public static function fraction(float $keep): string
    {
        return match (true) {
            abs($keep - 0.75) < 0.01 => 'بثلاثة أرباع الكمية',
            abs($keep - 2 / 3) < 0.01 => 'بثلثي الكمية',
            abs($keep - 0.5) < 0.01 => 'بنصف الكمية',
            default => 'بكمية أقل',
        };
    }
}
