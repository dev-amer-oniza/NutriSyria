<?php

namespace App\Data\Nutrition\References;

/** One rule as the references pages show it (MedicalReferences): what, how much, of which kind, and on what source. */
final readonly class ReferenceRule
{
    /**
     * @param  string  $kind  strict | tolerance | guidance | shortfall
     * @param  list<string>  $refs  keys of config/nutrition_references.php
     */
    public function __construct(
        public string $label,
        public string $value,
        public string $kind,
        public array $refs,
        public bool $verified,
        /** Shown, never judges a meal (total sugars, the +300 of "gain weight"). */
        public bool $infoOnly = false,
        public ?string $note = null,
    ) {}

    public function kindLabel(): string
    {
        return MedicalKind::label($this->infoOnly ? 'info' : $this->kind);
    }
}
