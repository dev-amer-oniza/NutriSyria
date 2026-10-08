<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodAlias extends Model
{
    protected $fillable = [
        'food_id', 'name_ar', 'name_en', 'search_normalized',
        'dialect', 'region_code', 'is_preferred',
    ];

    protected function casts(): array
    {
        return ['is_preferred' => 'boolean'];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
