<?php
namespace App\Models;

use App\Enums\ConfidenceLevel;
use App\Enums\PortionBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "1 cup of chickpeas = 164 g" — what converts a household measure into grams. */
class FoodPortion extends Model
{
    protected $fillable = [
        'food_id', 'measure_unit_id', 'amount', 'gram_weight', 'description',
        'food_source_record_id', 'food_form_id', 'basis', 'confidence_level',
        'is_default', 'valid_from', 'valid_to', 'evidence',
    ];

    /**
     * A portion entered on a source record's page belongs to that record's form. Store the form
     * on the portion itself, so it stays "raw" even if the portion is later dissociated or the
     * record deleted; otherwise it would turn form-less and apply to every form of the food.
     */
    protected static function booted(): void
    {
        static::saving(function (FoodPortion $portion) {
            if ($portion->food_form_id === null && $portion->food_source_record_id !== null) {
                $portion->food_form_id = FoodSourceRecord::whereKey($portion->food_source_record_id)->value('food_form_id');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount'           => 'decimal:4',
            'gram_weight'      => 'decimal:6',
            'basis'            => PortionBasis::class,
            'confidence_level' => ConfidenceLevel::class,
            'is_default'       => 'boolean',
            'valid_from'       => 'datetime',
            'valid_to'         => 'datetime',
            'evidence'         => 'array',
        ];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function measureUnit(): BelongsTo
    {
        return $this->belongsTo(MeasureUnit::class);
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(FoodSourceRecord::class, 'food_source_record_id');
    }
    /** اسم بديل: Filament يخمّن هذا الاسم تلقائياً من FoodSourceRecord عند الربط/فك الربط. */
    public function foodSourceRecord(): BelongsTo
    {
        return $this->sourceRecord();
    }

    public function foodForm(): BelongsTo
    {
        return $this->belongsTo(FoodForm::class);
    }
}
