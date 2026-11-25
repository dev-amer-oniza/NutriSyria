<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a person ate on one day, with the values it had then (App\Services\Daily\DailyLog;
 * nothing fillable). Values are null for a dish they asked us to add: it is shown, not counted.
 */
class MealLog extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'eaten_on' => 'date',
            'eaten_at' => 'datetime',
            'meal_type' => MealType::class,
            'amount' => 'float',
            'per_100g' => 'boolean',
            'energy_kcal' => 'float',
            'protein_g' => 'float',
            'carbohydrate_g' => 'float',
            'fat_g' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counted(): bool
    {
        return $this->energy_kcal !== null;
    }
}
