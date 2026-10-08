<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $modifier = $this->resource['modifier'];
        $pivot = $this->resource['pivot'];

        return [
            'id' => $modifier->id,
            'name' => $modifier->name,
            'price' => $modifier->price,
            'price_override' => $pivot->price_override,
            'is_available' => $pivot->is_available,
        ];
    }
}
