<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = ['brand_id', 'name', 'img', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(ProductRecipe::class);
    }
    /** اسم بديل: Filament يخمّن هذا الاسم أحياناً من ProductRecipe مباشرة. */
    public function productRecipes(): HasOne
    {
        return $this->recipe();
    }

    /** The calculated nutrition of this product (one row per nutrient), same shape as a food's. */
    public function nutrientValues(): HasMany
    {
        return $this->hasMany(FoodNutrientValue::class);
    }

    /** True once this product's nutrition has actually been calculated and stored. */
    protected function isAnalyzed(): Attribute
    {
        return Attribute::make(get: fn() => $this->nutrientValues()->exists());
    }
}
