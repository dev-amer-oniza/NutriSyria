<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nutrient extends Model
{
    protected $fillable = [
        'code', 'infoods_tagname', 'name_ar', 'name_en', 'unit',
        'group', 'display_order', 'is_core', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sourceCodes(): HasMany
    {
        return $this->hasMany(NutrientSourceCode::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(FoodNutrientValue::class);
    }
}
