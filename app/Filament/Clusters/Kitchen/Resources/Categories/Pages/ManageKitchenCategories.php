<?php

namespace App\Filament\Clusters\Kitchen\Resources\Categories\Pages;

use App\Filament\Clusters\Kitchen\Resources\Categories\KitchenCategoryResource;
use App\Models\Brand;
use App\Models\ProductCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKitchenCategories extends ManageRecords
{
    protected static string $resource = KitchenCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة تصنيف')
                ->using(function (array $data): ProductCategory {
                    $category = new ProductCategory(['name' => $data['name']]);
                    $category->brand_id = Brand::platform()->id;
                    $category->sort_order = (int) ProductCategory::where('brand_id', $category->brand_id)->max('sort_order') + 1;
                    $category->save();

                    return $category;
                }),
        ];
    }
}
