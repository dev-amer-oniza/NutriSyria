<?php

namespace App\Models;

use App\Auth\Access;
use App\Enums\ConsultationMode;
use App\Enums\NutritionistSpecialty;
use App\Enums\SyrianGovernorate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A nutritionist's public profile. It is listed on the site only when the account is an
 * active nutritionist with the consultations permission, the profile is complete, and
 * "accepting new clients" is on (the owner's decision).
 */
class NutritionistProfile extends Model
{
    protected $connection = 'central';

    public const MIN_BIO = 40;

    protected $fillable = [
        'display_name', 'photo', 'specialties', 'city', 'years_experience', 'bio',
        'consultation_mode', 'clinic_address', 'accepting_clients',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'city' => SyrianGovernorate::class,
            'years_experience' => 'integer',
            'consultation_mode' => ConsultationMode::class,
            'accepting_clients' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What still has to be filled before the profile can be listed (empty when complete). */
    public function missing(): array
    {
        return array_keys(array_filter([
            'الاسم المعروض' => blank($this->display_name),
            'تخصص واحد على الأقل' => blank($this->specialties),
            'المحافظة' => $this->city === null,
            'نبذة من '.self::MIN_BIO.' حرفاً على الأقل' => mb_strlen((string) $this->bio) < self::MIN_BIO,
            'طريقة الاستشارة' => $this->consultation_mode === null,
            'عنوان العيادة' => $this->consultation_mode?->needsAddress() && blank($this->clinic_address),
        ]));
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** Shown on the site: complete, accepting, and an active nutritionist account. */
    public function isListed(): bool
    {
        return $this->accepting_clients && $this->isComplete() && self::eligible($this->user);
    }

    public static function eligible(?User $user): bool
    {
        return $user !== null
            && $user->is_active !== false
            && $user->hasRole(Access::NUTRITIONIST)
            && $user->checkPermissionTo(Access::CONSULTATIONS_PROVIDE);
    }

    /**
     * The listing in SQL (the same rules as isListed, the bio length checked on save).
     * The role check runs on the users' connection, so it is applied in PHP by callers that
     * need it exactly; here the account must be active and the profile complete and open.
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('accepting_clients', true)
            ->whereNotNull('display_name')->whereNotNull('specialties')->whereNotNull('city')
            ->whereNotNull('bio')->whereNotNull('consultation_mode')
            ->whereHas('user', fn (Builder $u) => $u->where('is_active', true));
    }

    /** @return list<string> specialty labels */
    public function specialtyLabels(): array
    {
        return array_values(array_filter(array_map(
            fn (string $s) => NutritionistSpecialty::tryFrom($s)?->label(),
            $this->specialties ?? [],
        )));
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }
}
