<?php

namespace App\Filament\Clusters\Brands\Resources\Products\RelationManagers;

use App\Filament\Clusters\Brands\Resources\Products\Resources\ProductRecipes\ProductRecipeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class RecipeRelationManager extends RelationManager
{
    protected static string $relationship = 'recipe';

    protected static ?string $relatedResource = ProductRecipeResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
