<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasureUnit extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'dimension', 'base_factor', 'img', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_factor' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(MeasureUnitAlias::class);
    }

    public function foodPortions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
