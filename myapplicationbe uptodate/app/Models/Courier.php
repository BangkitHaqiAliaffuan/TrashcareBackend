<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Courier extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar_path',
        'vehicle_type',
        'vehicle_plate',
        'status',
        'is_available',
        'rating',
        'total_deliveries',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_available'     => 'boolean',
        'rating'           => 'float',
        'total_deliveries' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────

    /** Semua pickup yang ditangani kurir ini */
    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'courier_id');
    }

    /** Pickup yang sedang aktif (belum selesai / dibatalkan) */
    public function activePickups(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'courier_id')
                    ->whereIn('status', ['pending', 'on_the_way']);
    }
}
