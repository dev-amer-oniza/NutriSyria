<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Restaurants\Models\Restaurant;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'owner_id',
        'name',
        'status',
        'is_active',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];
    /**
     * Define which attributes have dedicated columns
     * (others will go into the 'data' JSON column)
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'owner_id',
            'name',
            'email',
            'is_active',
        ];
    }
    public function restaurant(): HasOne
    {
        return $this->hasOne(Restaurant::class, 'tenant_id');
    }
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function domain(): HasOne
    {
        return $this->hasOne(Domain::class, 'tenant_id');
    }
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->where(fn($q) => $q->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()))
            ->latestOfMany();
    }
}