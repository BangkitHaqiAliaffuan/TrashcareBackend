<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address',
        'latitude',
        'longitude',
        'pickup_date',
        'pickup_time',
        'status',
        'notes',
        'points_awarded',
        'estimated_weight_kg',
        'courier_id',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'pickup_date'    => 'date:Y-m-d',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
        'latitude'       => 'float',
        'longitude'      => 'float',
        'estimated_weight_kg' => 'float',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickupItem::class);
    }
}
