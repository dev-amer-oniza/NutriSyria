<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maps one source's own component code (e.g. USDA nutrient.id 1003) to one of our nutrients. */
class NutrientSourceCode extends Model
{
    protected $fillable = ['nutrient_id', 'source_system', 'code', 'factor', 'priority'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
}
