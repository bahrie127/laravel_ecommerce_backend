<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\HasRegionNames;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRegionNames, Notifiable;

    public const SELLER = 'seller';

    /** Nilai default kolom `roles` untuk pembeli. */
    public const BUYER = 'user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'country',
        'province',
        'city',
        'district',
        'postal_code',
        'roles',
        'photo',
        'is_livestreaming',
        'fcm_token',
    ];

    /**
     * `fcm_token` dulu ikut terkirim di daftar toko publik.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    protected $appends = ['province_name', 'city_name', 'district_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSeller(): bool
    {
        return $this->roles === self::SELLER;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
