<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FoodForm extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'group', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function foodSourceRecords(): HasMany
    {
        return $this->hasMany(FoodSourceRecord::class);
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
