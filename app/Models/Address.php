<?php

namespace App\Models;

use App\Models\Concerns\HasRegionNames;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Address extends Model
{
    use HasFactory, HasRegionNames;

    protected $fillable = [
        'user_id',
        'address',
        'country',
        'province',
        'city',
        'district',
        'postal_code',
        'is_default',
    ];

    /** Kolom wilayah menyimpan ID; nama ikut dikirim agar app tidak menampilkan angka. */
    protected $appends = ['province_name', 'city_name', 'district_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
