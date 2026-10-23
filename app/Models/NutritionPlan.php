<?php

namespace App\Models;

use App\Enums\EnergyEquation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A nutritionist's plan for one client link. Written only by ClientWorkspace (nothing fillable). */
class NutritionPlan extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'equation' => EnergyEquation::class,
            'body_fat_pct' => 'float',
            'adjustment_kcal' => 'integer',
            'carbs_pct' => 'integer',
            'protein_pct' => 'integer',
            'fat_pct' => 'integer',
            'fluids_ml' => 'integer',
            'meals' => 'array',
        ];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class, 'consultation_request_id');
    }

    /** The plan as NutritionPlanCalculator reads it (the activity level lives on the profile). */
    public function inputs(string $activity): array
    {
        return [
            'equation' => $this->equation->value,
            'body_fat_pct' => $this->body_fat_pct,
            'activity' => $activity,
            'adjustment' => $this->adjustment_kcal,
            'carbs_pct' => $this->carbs_pct,
            'protein_pct' => $this->protein_pct,
            'fat_pct' => $this->fat_pct,
            'fluids_ml' => $this->fluids_ml,
            'meals' => $this->meals ?? [],
        ];
    }
}
