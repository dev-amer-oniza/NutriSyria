<?php

namespace App\Data\Nutrition;

/**
 * One line of a recipe, as the analysis needs it: which approved record, how much of it, when.
 * Or one of the brand's preparations (preparationId, recordId 0), weighed in grams, which the
 * analysis opens into its own foods.
 */
final readonly class RecipeLine
{
    public function __construct(
        public int $recordId,
        public int $unitId,
        public float $amount,
        /** The portion size chosen on the line ("حبة وسط"), when the unit has several. */
        public ?int $portionId = null,
        public bool $addedAfterCooking = false,
        public ?int $preparationId = null,
    ) {}

    public function isPreparation(): bool
    {
        return $this->preparationId !== null;
    }

    /** @return array{record_id: int, unit_id: int, amount: float, portion_id: ?int} for GramsConverter::resolveMany */
    public function toConversion(): array
    {
        return ['record_id' => $this->recordId, 'unit_id' => $this->unitId, 'amount' => $this->amount, 'portion_id' => $this->portionId];
    }
}
