<?php

namespace App\Data\Nutrition;

/** How well one meal fits one person (MealFitCalculator). */
final readonly class MealFit
{
    /**
     * @param  int  $level  4 suits very well … 1 not for you now (config/site.php fit_levels)
     * @param  list<string>  $reasons  why, most important first
     * @param  array{energy: ?int, protein: ?int, carbs: ?int, fat: ?int}  $share  percent of the day's needs
     * @param  array{grams: int, kcal: ?int, whole: bool}|null  $portion  the largest portion within the limits; null when none suits
     * @param  list<string>  $notes  what is missing from the meal, or values we lack
     */
    public function __construct(
        public int $level,
        public array $reasons,
        public array $share,
        public ?array $portion,
        public array $notes,
        public string $template,
        /** "غداؤك في خطة أخصائيّك (14:00، 697 سعرة)" when judged against the plan's lunch */
        public ?string $basis = null,
        /** @var list<string> rules not verified yet that the meal goes past: shown, never judged */
        public array $info = [],
        /** "قيّمناها على: …": what judged the meal, and what waits for its references */
        public ?string $grounds = null,
        /** The person has conditions not verified yet: the verdict is "على احتياجك العام". */
        public bool $general = false,
        /** The card of the sources page (/sources#…) that holds this person's limits. */
        public ?string $sources = null,
    ) {}

    /** What the verdict is based on, for the person reading it. */
    public function who(): string
    {
        return 'حسب قالبك: '.$this->template.'.'.($this->basis ? ' مقارنةً ب'.$this->basis.'.' : '');
    }
}
