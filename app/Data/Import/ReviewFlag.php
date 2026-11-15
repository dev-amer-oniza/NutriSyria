<?php

namespace App\Data\Import;

/** One reason a reviewer should look at a record before approving it ("ناقص: الدهون"). */
final readonly class ReviewFlag
{
    public const MISSING_MACRO = 'missing_macro';

    public const ENERGY_MISMATCH = 'energy_mismatch';

    public const PROXIMATE_SUM = 'proximate_sum';

    public const PART_EXCEEDS_WHOLE = 'part_exceeds_whole';

    public const OUTSIDE_RANGE = 'outside_range';

    public const PROXY = 'proxy';

    public const LARGE_CHANGE = 'large_change';

    public function __construct(
        public string $code,
        /** Short badge text. */
        public string $label,
        /** The full reason, with the numbers. */
        public string $detail,
    ) {}
}
