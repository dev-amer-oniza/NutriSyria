<?php

namespace App\Filament\Clusters\Brands\Resources\Preparations\Pages;

use App\Filament\Clusters\Brands\Resources\Preparations\PreparationResource;
use App\Filament\Clusters\Brands\Resources\Products\ProductResource;
use App\Filament\Support\Concerns\HiddenFromAdmin;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

class CreatePreparation extends CreateRecord
{
    use HiddenFromAdmin;

    protected static string $resource = PreparationResource::class;

    /** The product page this was opened from («تحضير جديد»): back there, and there after saving. */
    #[Locked]
    public ?string $returnTo = null;

    public function mount(): void
    {
        parent::mount();

        // Only a product page of this panel (its full address, host included): a crafted link
        // cannot send anyone elsewhere.
        $url = request()->query('return');
        $products = ProductResource::getUrl('index');
        $this->returnTo = is_string($url) && ($url === $products.'/create' || preg_match('#^'.preg_quote($products, '#').'/\d+/edit$#', $url))
            ? $url : null;
    }

    /** «رجوع للمنتج» when opened from a product, else back to the list (App\Filament\Support\BackLink). */
    public function backLink(): array
    {
        return $this->returnTo
            ? ['url' => $this->returnTo, 'label' => 'رجوع للمنتج']
            : ['url' => PreparationResource::getUrl('index'), 'label' => 'رجوع إلى '.PreparationResource::getNavigationLabel()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->returnTo ?? parent::getRedirectUrl();
    }

    /** A single-brand owner never picks the brand: it is theirs. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['brand_id'] ??= PreparationResource::onlyBrandId();

        return $data;
    }
}
