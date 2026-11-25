<?php

namespace App\Models;

use App\Enums\MealType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing a person ate on one day, with the values it had then (App\Services\Daily\DailyLog;
 * nothing fillable). Values are null for a dish they asked us to add: it is shown, not counted.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $eaten_on
 * @property Carbon $eaten_at
 * @property MealType $meal_type
 * @property int|null $product_id
 * @property int|null $food_request_id
 * @property string $name
 * @property float $amount
 * @property bool $per_100g
 * @property float|null $energy_kcal
 * @property float|null $protein_g
 * @property float|null $carbohydrate_g
 * @property float|null $fat_g
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
