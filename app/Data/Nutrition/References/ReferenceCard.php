<?php

namespace App\Data\Nutrition\References;

/** A card of the references pages: the general needs, one template, or one health condition. */
final readonly class ReferenceCard
{
    public const VERIFIED = 'verified';

    public const PARTIAL = 'partial';

    public const PENDING = 'pending';

    /** No rule of its own, and no reference saying so: the general needs apply. */
    public const GENERAL = 'general';

    /**
     * @param  string  $type  general | template | condition
     * @param  list<ReferenceRule>  $rules
     * @param  list<string>  $refs  references of the card itself (a condition whose sources ask for no figures)
     * @param  array{key: string, label: string, anchor: string}|null  $template  the template a condition brings
     */
    public function __construct(
        public string $anchor,
        public string $type,
        public string $title,
        public string $group,
        public ?string $description,
        public array $rules,
        public array $refs,
        public string $status,
        public int $verifiedCount,
        public int $total,
        public ?array $template = null,
        public ?string $advice = null,
    ) {}

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::VERIFIED => 'موثّق',
            self::PARTIAL => "موثّق جزئياً {$this->verifiedCount}/{$this->total}",
            self::PENDING => 'قيد المراجعة',
            default => 'بلا حدود خاصة',
        };
    }

    /** Filament / site colour of the status. */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::VERIFIED => 'success',
            self::PARTIAL, self::PENDING => 'warning',
            default => 'gray',
        };
    }

    public function hasPending(): bool
    {
        return in_array($this->status, [self::PARTIAL, self::PENDING], true);
    }
}
