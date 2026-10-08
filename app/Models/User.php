<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Deliveries\Models\Delivery;
use Modules\Orders\Models\Order;
use Modules\Restaurants\Models\Restaurant;
use Modules\UserDietSection\Models\UserHealthProfile;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable , HasApiTokens , HasRoles;

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
        ];
    }

    public function restaurantRequest(): HasMany{
        return $this->hasMany(RestaurantRequest::class,'customer_id');
    }

    public function canAccessPanel(Panel $panel): bool{
        if($panel->getId() == 'admin'){
            return $this->hasRole('super-admin');
        }
        if($panel->getId() == 'app'){
            return $this->hasRole('restaurant-owner');
        }
        return false ;
    }
    public function tenants ():HasMany {
        return $this->hasMany(Tenant::class , 'owner_id');
    }

    public function healthProfile():HasOne{
        return $this->hasOne(UserHealthProfile::class , 'user_id');
    }
}
