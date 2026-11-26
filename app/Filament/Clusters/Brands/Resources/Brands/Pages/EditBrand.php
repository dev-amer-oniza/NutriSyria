<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Services\Brands\BrandLinks;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    /** For a brand owner this page is "مطعمي": titled with the restaurant's name, with the
     *  section's links (مطعمي، المنتجات) beside it, as on the list pages. */
    public function getTitle(): string
    {
        return BrandResource::ownedBrandIds() === null ? parent::getTitle() : $this->getRecord()->name;
    }

    public function getSubNavigation(): array
    {
        if (BrandResource::ownedBrandIds() === null || blank($cluster = static::getCluster())) {
            return parent::getSubNavigation();
        }

        return $this->generateNavigationItems($cluster::getClusteredComponents());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** @var list<int> the brand's managers before the save: only new links are told (BrandLinks) */
    protected array $managersBefore = [];

    protected function beforeSave(): void
    {
        $this->managersBefore = $this->record->managers()->pluck('users.id')->all();
    }

    protected function afterSave(): void
    {
        app(BrandLinks::class)->brandSaved($this->record, $this->managersBefore);
    }
}
