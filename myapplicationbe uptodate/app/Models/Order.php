<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'listing_id',
        'status',
        'total_price',
        'quantity',
        'notes',
        'shipping_address',
        'mayar_payment_id',
        'mayar_payment_link',
        'payment_status',
        'paid_at',
        'confirmed_at',
        'shipped_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'total_price'    => 'integer',
        'quantity'       => 'integer',
        'paid_at'        => 'datetime',
        'confirmed_at'   => 'datetime',
        'shipped_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    // ── Payment helpers ────────────────────────────────────────────
    public function isPaid(): bool       { return $this->payment_status === 'paid'; }
    public function isPaymentClosed(): bool { return $this->payment_status === 'closed'; }

    // ── Relationships ─────────────────────────────────────────────

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    // ── Status helpers ────────────────────────────────────────────

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isConfirmed(): bool  { return $this->status === 'confirmed'; }
    public function isShipped(): bool    { return $this->status === 'shipped'; }
    public function isCompleted(): bool  { return $this->status === 'completed'; }
    public function isCancelled(): bool  { return $this->status === 'cancelled'; }
}
