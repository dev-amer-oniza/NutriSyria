<?php

namespace App\Filament\Clusters\Brands\Resources\Brands\Pages;

use App\Filament\Clusters\Brands\Resources\Brands\BrandResource;
use App\Models\Brand;
use App\Services\Brands\BrandLinks;
use App\Services\Brands\ClientOwner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

/** "إضافة عميل": the account that runs it, its kind, then its details. */
class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    public function getTitle(): string
    {
        return 'إضافة عميل';
    }

    /** The client and its owner are saved together, or not at all. */
    protected function handleRecordCreation(array $data): Brand
    {
        return DB::transaction(function () use ($data) {
            $brand = Brand::create($data);
            app(ClientOwner::class)->assign($brand, (int) ($this->data['owner_id'] ?? 0));

            return $brand;
        });
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Brand) {
            app(BrandLinks::class)->brandSaved($this->record, []);
        }
    }
}
