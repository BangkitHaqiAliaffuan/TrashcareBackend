<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WasteCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'label',
        'emoji',
        'description',
        'points_per_kg',
        'is_active',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'points_per_kg' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function pickupItems(): HasMany
    {
        return $this->hasMany(PickupItem::class);
    }
}
