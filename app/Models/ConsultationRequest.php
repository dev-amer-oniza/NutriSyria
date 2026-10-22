<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's request to a nutritionist, and the link once accepted. Changed only through
 * App\Services\Consultations\ConsultationService, so nothing here is fillable.
 */
class ConsultationRequest extends Model
{
    protected $connection = 'central';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => ConsultationStatus::class,
            'consented_at' => 'datetime',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
            'ended_at' => 'datetime',
            'client_notified_at' => 'datetime',
        ];
    }

    /** The person asking. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nutritionist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nutritionist_id');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', ConsultationStatus::OPEN);
    }

    public function isPending(): bool
    {
        return $this->status === ConsultationStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === ConsultationStatus::Accepted;
    }

    /** Days left before a pending request expires (at least 1 while it is still pending). */
    public function daysLeft(): int
    {
        return max(1, (int) ceil(now()->diffInHours($this->expires_at, false) / 24));
    }

    /** What the nutritionist may know before accepting: age, sex and goal ("34 سنة، أنثى، إنقاص الوزن"). */
    public function clientSummary(): ?string
    {
        $profile = $this->user->profile;

        return implode('، ', array_filter([
            $profile?->age() ? $profile->age().' سنة' : null,
            $profile?->sex?->label(),
            $profile?->goal?->label(),
        ])) ?: null;
    }

    /** A wa.me link for a number stored as +9639…, or null. */
    public static function whatsappUrl(?string $phone): ?string
    {
        return $phone ? 'https://wa.me/'.ltrim($phone, '+') : null;
    }
}
