<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A section of one restaurant's menu; a product sits in at most one. */
class ProductCategory extends Model
{
    protected $fillable = ['name', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function isPlatform(): bool
    {
        return (bool) $this->loadMissing('brand')->brand?->is_platform;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /** Menu order: the owner's order, then by name. */
    public function scopeMenuOrder(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
