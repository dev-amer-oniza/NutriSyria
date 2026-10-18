<?php
namespace App\Filament\Clusters\Brands\Resources\Products\Pages;

use App\Filament\Clusters\Brands\Pages\RecipeAnalysis;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeRecipe')
                ->label('تحليل الوصفة')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->url(fn() => RecipeAnalysis::getUrl() . '?product=' . $this->record->id)
                ->visible(fn () => auth()->user()?->can('view', $this->record) ?? false),
                
            DeleteAction::make(),
        ];
    }
}
