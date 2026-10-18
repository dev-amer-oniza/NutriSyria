<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Read-only product page: the nutritionist reviews the recipe here (the ingredient list is
 * read-only on a view page) and goes on to the analysis to approve it.
 */
class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeRecipe')
                ->label('تحليل الوصفة')
                ->icon(Heroicon::OutlinedBeaker)
                ->url(fn () => RecipeAnalysis::getUrl().'?product='.$this->record->id),
            EditAction::make(),
        ];
    }
}
