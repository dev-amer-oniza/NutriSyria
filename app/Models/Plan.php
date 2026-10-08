<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'code', 'price', 'billing_interval', 'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $connection = 'central' ;

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_feature')
                    ->withPivot('value')
                    ->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // Convenience: get a feature value by code
    public function featureValue(string $code): mixed
    {
        $feature = $this->features->firstWhere('code', $code);

        return $feature?->pivot->value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}