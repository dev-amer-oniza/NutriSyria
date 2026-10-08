<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'menu_item_id' => $this->menu_item_id,
            'menu_item_name' => $this->menuItem?->name,
            'total_grams' => (float) $this->total_grams,
            'nutrients' => [
                'energy_kcal' => $this->nutrientValue('energy_kcal', 'kcal'),
                'protein_g' => $this->nutrientValue('protein_g', 'g'),
                'fat_total_g' => $this->nutrientValue('fat_total_g', 'g'),
                'carbs_g' => $this->nutrientValue('carbs_g', 'g'),
                'fiber_g' => $this->nutrientValue('fiber_g', 'g'),
                'sugars_total_g' => $this->nutrientValue('sugars_total_g', 'g'),
                'calcium_mg' => $this->nutrientValue('calcium_mg', 'mg'),
                'iron_mg' => $this->nutrientValue('iron_mg', 'mg'),
                'sodium_mg' => $this->nutrientValue('sodium_mg', 'mg'),
                'potassium_mg' => $this->nutrientValue('potassium_mg', 'mg'),
                'vitamin_c_mg' => $this->nutrientValue('vitamin_c_mg', 'mg'),
                'vitamin_a_rae_ug' => $this->nutrientValue('vitamin_a_rae_ug', 'µg'),
            ],
        ];
    }
    private function nutrientValue(string $column, string $unit): array
    {
        return [
            'value' => $this->{$column} !== null ? (float) $this->{$column} : null,
            'unit' => $unit,
        ];
    }
}
