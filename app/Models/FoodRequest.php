<?php

namespace App\Models;

use App\Enums\FoodRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dish someone ate and could not find, sent to the team with what is in it. Changed only
 * through App\Services\Daily\FoodRequests, so nothing here is fillable.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $name_key
 * @property string $ingredients
 * @property FoodRequestStatus $status
 * @property int|null $product_id
 * @property string|null $admin_note
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read Product|null $product
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

    /**
     * The meal it became, once added.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isPending(): bool
    {
        return $this->status === FoodRequestStatus::Pending;
    }
}
