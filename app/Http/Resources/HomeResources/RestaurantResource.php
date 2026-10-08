<?php

namespace App\Http\Resources\HomeResources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantResource extends BaseRestaurantResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge($this->baseData(), [

            'cover_image' => $this->cover_image
                ? $this->cover_image
                : null,

            'categories' => CategoryResource::collection(
                $this->whenLoaded('categories')
            ),
        ]);
    }
}
