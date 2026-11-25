<?php

namespace App\Enums;

/**
 * Which meal of the day something was eaten at. The hour alone picks breakfast, lunch or
 * dinner (the owner's decision 2a: until 11, until 17, then dinner); a snack is chosen by hand.
 */
enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';

    public const LUNCH_FROM = 11;

    public const DINNER_FROM = 17;

    public static function forHour(int $hour): self
    {
        return match (true) {
            $hour < self::LUNCH_FROM => self::Breakfast,
            $hour < self::DINNER_FROM => self::Lunch,
            default => self::Dinner,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => 'فطور',
            self::Lunch => 'غدا',
            self::Dinner => 'عشا',
            self::Snack => 'سناك',
        };
    }

    /** "شو بيناسبك على الفطور؟" */
    public function onLabel(): string
    {
        return match ($this) {
            self::Breakfast => 'الفطور',
            self::Lunch => 'الغدا',
            self::Dinner => 'العشا',
            self::Snack => 'السناك',
        };
    }

    /**
     * Words a menu section's name holds when it is this meal ("الفطور"، "وجبات الغداء"),
     * compared after ArabicNormalizer. A snack has none: any meal may be one.
     *
     * @return list<string>
     */
    public function sectionWords(): array
    {
        return match ($this) {
            self::Breakfast => ['فطور', 'ترويقه', 'صباح'],
            self::Lunch => ['غدا'],
            self::Dinner => ['عشا'],
            self::Snack => [],
        };
    }
}
