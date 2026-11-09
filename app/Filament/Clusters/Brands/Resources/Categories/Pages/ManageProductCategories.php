<?php

namespace App\Filament\Clusters\Brands\Resources\Categories\Pages;

use App\Filament\Clusters\Brands\Resources\Categories\ProductCategoryResource;
use App\Models\ProductCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductCategories extends ManageRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة تصنيف')
                // brand_id is not fillable: set here, once the form checked it is one of the user's (or
                // it is their only restaurant, when the field is hidden).
                ->using(function (array $data): ProductCategory {
                    $category = new ProductCategory(['name' => $data['name']]);
                    $category->brand_id = (int) ($data['brand_id'] ?? ProductCategoryResource::onlyBrandId());
                    $category->sort_order = (int) ProductCategory::where('brand_id', $category->brand_id)->max('sort_order') + 1;
                    $category->save();

                    return $category;
                }),
        ];
    }
}
