<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\Pages;

use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\ProductRecipeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductRecipe extends EditRecord
{
    protected static string $resource = ProductRecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
