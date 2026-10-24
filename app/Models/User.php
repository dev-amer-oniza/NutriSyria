<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Auth\Access;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'latitude',
        'longitude',
        'email_verified_at',
        'is_active',
    ];

    protected $connection = 'central';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }


    /**
     * Only active accounts with one of the three panel roles get in. Filament asks on every
     * request, so deactivating a user or removing their role locks them out immediately.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->is_active !== false
            && $this->hasAnyRole(Access::PANEL_ROLES);
    }

    /**
     * Panel notifications live with the users, on the "central" connection (Laravel's default
     * model would use the default connection).
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(CentralDatabaseNotification::class, 'notifiable')->latest();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Access::SUPER_ADMIN);
    }

    /** A permission check that never throws for an unknown permission; the super-admin has all. */
    public function allows(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->checkPermissionTo($permission);
    }

    /** The nutrition profile filled in on the site (null until the first step). */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** A nutritionist's public profile on the site. */
    public function nutritionistProfile(): HasOne
    {
        return $this->hasOne(NutritionistProfile::class);
    }

    /** The consultations this person asked for (App\Services\Consultations\ConsultationService). */
    public function consultationRequests(): HasMany
    {
        return $this->hasMany(ConsultationRequest::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_user')->withTimestamps();
    }

    /** @var list<int>|null ids of the brands this user manages, loaded once per request */
    private ?array $managedBrandIds = null;

    /** @return list<int> */
    public function managedBrandIds(): array
    {
        return $this->managedBrandIds ??= DB::table('brand_user')
            ->where('user_id', $this->getKey())
            ->pluck('brand_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function managesBrand(?int $brandId): bool
    {
        return $brandId !== null && in_array($brandId, $this->managedBrandIds(), true);
    }
}
