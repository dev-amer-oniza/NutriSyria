<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Restaurants\Models\MenuItem;
use Modules\Restaurants\Models\MenuItemVariant;
use Stancl\Tenancy\Facades\Tenancy;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        ['item' => $item, 'restaurant' => $restaurant, 'details' => $details] = $this->resource;
        Tenancy::initialize($this->resource['restaurant']['tenant_id']);

        $data = [
            'id' => $item->id,
            'restaurant_id' => $restaurant->id,
            'name' => $item->name,
            'description' => $item->description,
            'price' => $item->startingPrice(),
            'image' => $item->image,
            'is_featured' => $item->is_featured,
            'preparation_time' => $item->formattedPreparationTime(),
            'is_nutritionally_analyzed' => $item->is_nutritionally_analyzed,
        ];

        if ($details) {
            $data['variants'] = MenuItemVariantResource::collection(
                $item->variants->map(fn($variant) => [
                    'variant' => $variant,
                    'restaurant' => $this->resource['restaurant'],
                ])
            )->values();


            $data['modifier_groups'] = $item->modifierGroupsWithModifiers
                ->groupBy('modifier_group_id')
                ->map(function ($rows) {
                    $group = $rows->first()->modifierGroup;
                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'is_required' => $group->is_required,
                        'is_multiple' => $group->is_multiple,
                        'min_selections' => $group->min_selections,
                        'max_selections' => $group->max_selections,
                        'modifiers' => $rows->map(fn($row) => [
                            'id' => $row->modifier->id,
                            'name' => $row->modifier->name,
                            'price' => $row->modifier->price,
                            'price_override' => $row->price_override,
                            'is_available' => $row->is_available,
                        ])->values(),
                    ];
                })->values();
        }

        Tenancy::end();

        return $data;

    }
}
