<?php

namespace App\Enums;

/**
 * How a person's day goes without exercise (the owner's decision, Nov 2026); the factors are in
 * config/nutrition_criteria.php (daily_life), from FAO/WHO/UNU 2004 Table 5.1.
 */
enum DailyLife: string
{
    case Home = 'home';
    case Seated = 'seated';
    case Standing = 'standing';
    case Physical = 'physical';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'بالبيت أغلب الوقت',
            self::Seated => 'شغل أو دراسة وأنت قاعد',
            self::Standing => 'شغل وأنت واقف ومتحرك',
            self::Physical => 'شغل جسدي',
        };
    }

    /** Examples from people's lives, so the choice is clear. */
    public function examples(): string
    {
        return match ($this) {
            self::Home => 'قعدة وتلفون وتلفزيون، بلا شغل يومي',
            self::Seated => 'موظف مكتب، طالب جامعة، سائق',
            self::Standing => 'أستاذ، بائع، ممرض، نادل',
            self::Physical => 'بناء، نجارة، حمل، زراعة',
        };
    }

    /** A job or study: asked how many days a week; the other days count as home. */
    public function hasWorkDays(): bool
    {
        return $this !== self::Home;
    }

    public function factor(): float
    {
        return (float) config("nutrition_criteria.daily_life.{$this->value}");
    }
}
