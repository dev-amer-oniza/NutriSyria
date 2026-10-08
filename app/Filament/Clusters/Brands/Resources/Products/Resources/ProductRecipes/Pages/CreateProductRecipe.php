<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Pages;

use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\ProductRecipeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductRecipe extends CreateRecord
{
    protected static string $resource = ProductRecipeResource::class;
}
