<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModifierGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $group = $this->resource;

        return [
            'id' => $group->id,
            'name' => $group->name,
            'is_required' => $group->is_required,
            'is_multiple' => $group->is_multiple,
            'min_selections' => $group->min_selections,
            'max_selections' => $group->max_selections,
            'modifiers' => $group->modifiers->map(fn($modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'price_override' => $modifier->pivot->price_override,
                'is_available' => $modifier->pivot->is_available,
            ])->values(),
        ];
    }
}
