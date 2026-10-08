<?php

namespace App\Models;

use App\Enums\FoodSourceStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One source's description of one food in one form (e.g. "USDA item 173757 = chickpeas,
 * boiled"). source_type/name/country/priority are NOT columns here: they come from dataSource.
 * The unique (data_source_id, external_ref) pair is the crosswalk a re-import matches against.
 */
class FoodSourceRecord extends Model
{
    protected $fillable = [
        'food_id', 'food_form_id', 'data_source_id', 'import_batch_id', 'external_ref',
        'status', 'is_preferred', 'refuse_pct', 'valid_from', 'valid_to',
        'imported_at', 'img', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => FoodSourceStatus::class,
            'is_preferred' => 'boolean',
            'refuse_pct' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function foodForm(): BelongsTo
    {
        return $this->belongsTo(FoodForm::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function nutrientValues(): HasMany
    {
        return $this->hasMany(FoodNutrientValue::class);
    }

    /** Portions imported specifically for this source record (see food_portions.food_source_record_id). */
    public function portions(): HasMany
    {
        return $this->hasMany(FoodPortion::class);
    }

    /** Present only when this record is a calculated dish (dataSource type = recipe). */
    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    /** Recipe lines that use this record as an ingredient (e.g. this garlic, used in many dishes). */
    public function usedAsIngredientIn(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', FoodSourceStatus::Active);
    }

    #[Scope]
    protected function pendingReview(Builder $query): void
    {
        $query->where('status', FoodSourceStatus::PendingReview);
    }
}
