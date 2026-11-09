<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords
{
    protected static string $resource = BrandResource::class;

    /** An owner with one restaurant goes straight to it ("مطعمي"). */
    public function mount(): void
    {
        if ($id = BrandResource::ownBrandId()) {
            $this->redirect(BrandResource::getUrl('edit', ['record' => $id]));

            return;
        }

        parent::mount();
    }

    public function getTitle(): string
    {
        return BrandResource::getNavigationLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
