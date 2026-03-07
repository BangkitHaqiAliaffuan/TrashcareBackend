<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_request_id',
        'waste_category_id',
        'estimated_weight_kg',
        'actual_weight_kg',
        'notes',
    ];

    protected $casts = [
        'estimated_weight_kg' => 'float',
        'actual_weight_kg'    => 'float',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    public function wasteCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class);
    }
}
