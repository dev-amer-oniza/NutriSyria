<?php

namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Read-only product page: the nutritionist reviews the recipe here (the ingredient list is
 * read-only on a view page) and goes on to the analysis to approve it. Whoever may edit the
 * product (its brand owner, the admin) is sent to the edit page instead.
 */
class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (auth()->user()?->can('update', $this->record)) {
            $this->redirect(ProductResource::getUrl('edit', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeRecipe')
                ->label('تحليل الوصفة')
                ->icon(Heroicon::OutlinedBeaker)
                ->url(fn () => RecipeAnalysis::getUrl().'?product='.$this->record->id),
        ];
    }
}
