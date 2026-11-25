<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One weighing on one day (App\Services\Daily\Weighing; nothing fillable).
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $weighed_on
 * @property float $weight_kg
 */
class WeightLog extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'weighed_on' => 'date',
            'weight_kg' => 'float',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
