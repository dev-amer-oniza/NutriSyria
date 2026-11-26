<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Models\Brand;
use App\Services\Brands\BrandLinks;
use App\Services\Brands\ClientOwner;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    /** For a brand owner this page is "مطعمي": titled with the restaurant's name, with the
     *  section's links (مطعمي، المنتجات) beside it, as on the list pages. */
    public function getTitle(): string
    {
        $brand = $this->getRecord();

        return BrandResource::ownedBrandIds() === null && $brand instanceof Brand
            ? 'العميل: '.$brand->name
            : $brand->name;
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

    /** The admin sees who runs the client (ClientOwner). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $brand = $this->record;
        if ($brand instanceof Brand && auth()->user()?->isSuperAdmin()) {
            $data['owner_id'] = app(ClientOwner::class)->of($brand);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $brand = $this->record;
        $this->managersBefore = $brand instanceof Brand ? array_map('intval', $brand->managers()->pluck('users.id')->all()) : [];
    }

    /** Only the admin picks the owner; a new one is told (BrandLinks), the old one simply loses it. */
    protected function afterSave(): void
    {
        $brand = $this->record;
        if (! $brand instanceof Brand) {
            return;
        }

        $owner = (int) ($this->data['owner_id'] ?? 0);
        if (auth()->user()?->isSuperAdmin() && $owner && $owner !== app(ClientOwner::class)->of($brand)) {
            app(ClientOwner::class)->assign($brand, $owner);
        }

        app(BrandLinks::class)->brandSaved($brand, $this->managersBefore);
    }
}
