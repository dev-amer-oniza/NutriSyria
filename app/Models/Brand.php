<?php

namespace App\Models;

use App\Enums\SyrianGovernorate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'name', 'description', 'logo', 'phone_1', 'phone_2', 'has_delivery', 'is_active',
        'city', 'address', 'opening_hours', 'whatsapp', 'instagram_url', 'facebook_url', 'delivery_note',
    ];

    protected function casts(): array
    {
        return [
            'has_delivery' => 'boolean',
            'is_active' => 'boolean',
            'city' => SyrianGovernorate::class,
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** The sections of this brand's menu, in the owner's order. */
    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class)->menuOrder();
    }

    /** The users who manage this brand (brand owners). */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'brand_user')->withTimestamps();
    }

    /** Brands the user manages; read straight from the pivot, so it works across connections. */
    public function scopeManagedBy(Builder $query, User $user): void
    {
        $query->whereIn($query->qualifyColumn('id'), $user->managedBrandIds());
    }
}
