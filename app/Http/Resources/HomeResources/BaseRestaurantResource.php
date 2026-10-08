<?php

namespace App\Http\Resources\HomeResources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseRestaurantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function baseData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'phone' => $this->phone,

            'logo' => $this->logo ? $this->logo  : null,

            'hours' => [
                'opens' => Carbon::parse($this->opening_time)->format('h:i A'),
                'closes' => Carbon::parse($this->closing_time)->format('h:i A'),
                'is_open' => $this->isOpenNow(),
            ],

            'location' => [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'distance_km' => isset($this->distance)
                    ? round($this->distance, 2)
                    : null,
            ],

            'rate' => $this->rate,
        ];
    }
}
