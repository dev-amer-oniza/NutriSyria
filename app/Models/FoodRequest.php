<?php

namespace App\Models;

use App\Enums\FoodRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dish someone ate and could not find, sent to the team with what is in it. Changed only
 * through App\Services\Daily\FoodRequests, so nothing here is fillable.
 */
class FoodRequest extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => FoodRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The meal it became, once added. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isPending(): bool
    {
        return $this->status === FoodRequestStatus::Pending;
    }
}
