<?php

namespace App\Filament\Clusters\Kitchen\Resources\Dishes\Pages;

use App\Filament\Clusters\Kitchen\Pages\KitchenRecipeAnalysis;
use App\Filament\Clusters\Kitchen\Resources\Dishes\KitchenDishResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKitchenDish extends EditRecord
{
    protected static string $resource = KitchenDishResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeRecipe')
                ->label('تحليل الوجبة')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->url(fn () => KitchenRecipeAnalysis::getUrl().'?product='.$this->record->id),
            DeleteAction::make(),
        ];
    }
}
