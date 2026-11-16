<?php

namespace App\Data\Nutrition;

/** One line of a recipe, as the analysis needs it: which approved record, how much of it, when. */
final readonly class RecipeLine
{
    public function __construct(
        public int $recordId,
        public int $unitId,
        public float $amount,
        /** The portion size chosen on the line ("حبة وسط"), when the unit has several. */
        public ?int $portionId = null,
        public bool $addedAfterCooking = false,
    ) {}

    /** @return array{record_id: int, unit_id: int, amount: float, portion_id: ?int} for GramsConverter::resolveMany */
    public function toConversion(): array
    {
        return ['record_id' => $this->recordId, 'unit_id' => $this->unitId, 'amount' => $this->amount, 'portion_id' => $this->portionId];
    }
}
