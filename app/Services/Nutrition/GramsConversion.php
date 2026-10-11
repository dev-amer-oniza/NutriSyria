<?php

namespace App\Services\Nutrition;

/**
 * The outcome of converting one recipe line ("2 cups of boiled chickpeas") to grams. A failed
 * conversion keeps grams NULL and says why, so callers can show the reason instead of quietly
 * treating the ingredient as zero grams.
 */
final readonly class GramsConversion
{
    public const OK = 'ok';
    public const UNKNOWN_UNIT = 'unknown_unit';
    public const UNKNOWN_RECORD = 'unknown_record';
    public const INVALID_AMOUNT = 'invalid_amount';
    public const NO_PORTION = 'no_portion';
    public const OTHER_FORM_ONLY = 'other_form_only';
    public const AMBIGUOUS_PORTION = 'ambiguous_portion';

    public const METHOD_MASS = 'mass';                       // g, kg, mg, oz, lb via measure_units.base_factor
    public const METHOD_PORTION = 'portion';                 // a food_portions row in the same unit
    public const METHOD_VOLUME_DENSITY = 'volume_density';   // ml/l/fl oz via a portion in another volume unit
    public const METHOD_NONE = 'none';

    public function __construct(
        public ?float $grams,
        public string $status,
        public string $method,
        /** Arabic, for the admin: why the conversion failed. */
        public ?string $message = null,
        /** Arabic, for the admin: which portion or factor produced the grams. */
        public ?string $basis = null,
        public ?int $portionId = null,
    ) {
    }

    public static function converted(float $grams, string $method, ?string $basis = null, ?int $portionId = null): self
    {
        return new self($grams, self::OK, $method, null, $basis, $portionId);
    }

    public static function failed(string $status, string $message): self
    {
        return new self(null, $status, self::METHOD_NONE, $message);
    }

    public function ok(): bool
    {
        return $this->grams !== null;
    }
}
