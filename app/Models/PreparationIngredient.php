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

    /** @return BelongsTo<Preparation, $this> */
    public function preparation(): BelongsTo
    {
        return $this->belongsTo(Preparation::class);
    }

    /** @return BelongsTo<FoodSourceRecord, $this> */
    public function ingredientRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }

    /** @return BelongsTo<MeasureUnit, $this> */
    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }
}
