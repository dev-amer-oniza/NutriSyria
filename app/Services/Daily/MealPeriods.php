<?php

namespace App\Services\Daily;

use App\Data\Nutrition\MealFacts;
use App\Enums\MealType;
use App\Models\ProductCategory;
use App\Services\Import\ArabicNormalizer;
use App\Services\Site\PublicCatalogCache;

/**
 * Which meal of the day a dish is, read from its menu section's name ("الفطور", "وجبات الغداء").
 * Built once per catalog version (one query), shared by every visitor.
 */
class MealPeriods
{
    public function __construct(private readonly PublicCatalogCache $cache) {}

    public function of(MealFacts $meal): ?MealType
    {
        $value = $meal->categoryId === null ? null : ($this->map()[$meal->categoryId] ?? null);

        return $value === null ? null : MealType::from($value);
    }

    /** @return array<int, string> category id => MealType value, for sections that name one */
    private function map(): array
    {
        return $this->cache->remember('meal-periods', fn () => ProductCategory::query()
            ->pluck('name', 'id')
            ->map(fn (string $name) => self::typeOf($name)?->value)
            ->filter()
            ->all());
    }

    public static function typeOf(string $sectionName): ?MealType
    {
        $name = ArabicNormalizer::normalize($sectionName);
        foreach ([MealType::Breakfast, MealType::Lunch, MealType::Dinner] as $type) {
            foreach ($type->sectionWords() as $word) {
                if (str_contains($name, $word)) {
                    return $type;
                }
            }
        }

        return null;
    }
}
