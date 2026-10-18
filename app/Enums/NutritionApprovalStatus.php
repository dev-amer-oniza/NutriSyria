<?php

namespace App\Enums;

use App\Models\Product;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Where a product's official nutrition values stand, for the brand owner and the nutritionist.
 * Read from a product loaded through withApprovalData(). "Changed" catches an ingredient added
 * or edited after the approval; a removed ingredient leaves no trace and is not detected.
 */
enum NutritionApprovalStatus: string implements HasLabel, HasColor, HasIcon
{
    case NoRecipe = 'no_recipe';
    case ChangesRequested = 'changes_requested';
    case Awaiting = 'awaiting';
    case Changed = 'changed';
    case Approved = 'approved';

    public static function withApprovalData(Builder $query): Builder
    {
        return $query
            ->with('recipe')
            ->withCount('recipeIngredients')
            ->withExists('nutrientValues as has_official_values')
            ->withExists(['reviewNotes as has_open_notes' => fn (Builder $q) => $q->whereNull('resolved_at')])
            ->withMax('recipeIngredients as ingredients_changed_at', 'product_recipe_ingredients.updated_at');
    }

    public static function of(Product $product): self
    {
        // The nutritionist asked for a change: the brand owner's turn, whatever else holds.
        if ($product->has_open_notes) {
            return self::ChangesRequested;
        }
        if ((int) $product->recipe_ingredients_count === 0) {
            return self::NoRecipe;
        }
        if (! $product->has_official_values) {
            return self::Awaiting;
        }

        $approvedAt = $product->recipe?->nutrition_approved_at;
        $changedAt = $product->ingredients_changed_at ? Carbon::parse($product->ingredients_changed_at) : null;
        $recipeChangedAt = $product->recipe?->updated_at;
        $lastChange = collect([$changedAt, $recipeChangedAt])->filter()->max();

        return $approvedAt && $lastChange && $lastChange->gt($approvedAt) ? self::Changed : self::Approved;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::NoRecipe => 'بلا وصفة',
            self::ChangesRequested => 'مطلوب تعديل',
            self::Awaiting => 'ينتظر اعتماد الأخصائي',
            self::Changed => 'تغيّرت الوصفة بعد الاعتماد',
            self::Approved => 'قيم رسمية معتمدة',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NoRecipe => 'gray',
            self::ChangesRequested => 'danger',
            self::Awaiting, self::Changed => 'warning',
            self::Approved => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::NoRecipe => Heroicon::OutlinedMinusCircle,
            self::ChangesRequested => Heroicon::OutlinedChatBubbleLeftEllipsis,
            self::Awaiting => Heroicon::OutlinedClock,
            self::Changed => Heroicon::OutlinedExclamationTriangle,
            self::Approved => Heroicon::OutlinedCheckBadge,
        };
    }

    /** Needs the nutritionist: nothing approved yet, or the recipe moved on since. */
    public function needsReview(): bool
    {
        return $this === self::Awaiting || $this === self::Changed;
    }
}
