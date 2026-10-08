<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Stancl\Tenancy\Facades\Tenancy;

class MenuItemVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['variant' => $variant] = $this->resource;
        Tenancy::initialize($this->resource['restaurant']['tenant_id']);

        $data = [
            'id' => $variant->id,
            'name' => $variant->name,
            'price' => $variant->price,
            'is_available' => $variant->is_available,

        ];
        Tenancy::end();

        return $data;
    }
}
