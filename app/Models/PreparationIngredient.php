<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One food of a preparation, written like a product line (approved records only). */
class PreparationIngredient extends Model
{
    protected $fillable = ['preparation_id', 'food_source_record_id', 'measure_unit_id', 'food_portion_id', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'sort_order' => 'integer'];
    }

    public function preparation(): BelongsTo
    {
        return $this->belongsTo(Preparation::class);
    }

    public function ingredientRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }
}
