<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketplaceListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'description',
        'price',
        'category',
        'condition',
        'image_path',
        'seller_name',
        'seller_rating',
        'is_sold',
        'is_active',
        'views_count',
    ];

    protected $casts = [
        'price'         => 'integer',
        'seller_rating' => 'float',
        'is_sold'       => 'boolean',
        'is_active'     => 'boolean',
        'views_count'   => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'listing_id');
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'listing_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_sold', false);
    }

    public function scopeByCategory($query, ?string $category = null)
    {
        if ($category && strtolower($category) !== 'all') {
            return $query->where('category', strtolower($category));
        }
        return $query;
    }
}
