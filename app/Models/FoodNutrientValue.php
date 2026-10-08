<?php
namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\NutrientValueMethod;
use App\Enums\ValueQualifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodNutrientValue extends Model
{
    protected $fillable = [
        'food_source_record_id', 'nutrient_id', 'amount_per_100g', 'unit', 'product_id',
        'value_qualifier', 'method', 'confidence_level', 'sample_count',
        'min_amount', 'max_amount', 'measurement_uncertainty_pct', 'sampled_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_per_100g'             => 'decimal:6',
            'value_qualifier'             => ValueQualifier::class,
            'method'                      => NutrientValueMethod::class,
            'confidence_level'            => ConfidenceLevel::class,
            'sample_count'                => 'integer',
            'min_amount'                  => 'decimal:6',
            'max_amount'                  => 'decimal:6',
            'measurement_uncertainty_pct' => 'decimal:3',
            'sampled_at'                  => 'datetime',
        ];
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function isForProduct(): bool
    {
        return $this->product_id !== null;
    }
    protected static function booted(): void
    {
        static::saving(function (self $value) {
            if ($value->nutrient_id) {
                $value->unit = $value->nutrient?->unit ?? $value->unit;
            }
        });
    }
}
