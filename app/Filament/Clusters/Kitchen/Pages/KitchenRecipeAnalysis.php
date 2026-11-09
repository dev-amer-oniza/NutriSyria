<?php

namespace App\Filament\Clusters\Kitchen\Pages;

use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Kitchen\KitchenCluster;
use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;

/**
 * The same analysis as a restaurant's product, for a kitchen dish: the admin approves it
 * directly, with no review notes (the owner's decisions). mount() still checks the dish is
 * the platform kitchen's (ProductPolicy::view).
 */
class KitchenRecipeAnalysis extends RecipeAnalysis
{
    protected static ?string $cluster = KitchenCluster::class;

    protected static ?string $slug = 'analysis';

    protected static ?string $title = 'تحليل الوجبة';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    protected static function visibleToAdmin(): bool
    {
        return true;
    }

    protected function productUrl(): string
    {
        return KitchenDishResource::getUrl('edit', ['record' => $this->product]);
    }
}
