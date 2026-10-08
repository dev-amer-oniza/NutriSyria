<?php

namespace App\Http\Resources\HomeResources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantByCategoryResource extends BaseRestaurantResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return array_merge($this->baseData(), [

            'categories' => $this->whenLoaded('categories', function () {
                return $this->categories->map(fn($cat) => [
                    //'id' => $cat->id,
                    'name' => $cat->name,
                ]);
            }),
        ]);
    }
}
