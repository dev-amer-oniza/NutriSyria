<?php

namespace App\Http\Resources\HomeResources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
           // 'slug'  => $this->slug,
          //  'icon'  => $this->icon,
            'image' => $this->img ? $this->img : null,
        ];
    }
}
