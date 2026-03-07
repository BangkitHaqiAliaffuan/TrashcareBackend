<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/wishlist
    // Returns all wishlisted listings for the authenticated user
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Eager-load listing (including soft-deleted via withTrashed so history is kept)
        $wishlists = $user->wishlists()
            ->with(['listing' => fn ($q) => $q->withTrashed()])
            ->latest()
            ->get();

        $wishlistedIds = $wishlists->pluck('listing_id')->toArray();

        $data = $wishlists
            ->filter(fn ($w) => $w->listing !== null) // skip orphaned records
            ->map(fn ($w) => $this->formatListing($w->listing, $wishlistedIds))
            ->values();

        return response()->json(['data' => $data]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/wishlist/toggle
    // Toggle wishlist status for a listing.
    // Body: { listing_id }
    // Response: { wishlisted: true/false, message }
    // ─────────────────────────────────────────────────────────────
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:marketplace_listings,id'],
        ]);

        $user      = $request->user();
        $listingId = $validated['listing_id'];

        $existing = Wishlist::where('user_id', $user->id)
            ->where('listing_id', $listingId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'wishlisted' => false,
                'message'    => 'Dihapus dari wishlist.',
            ]);
        }

        Wishlist::create([
            'user_id'    => $user->id,
            'listing_id' => $listingId,
        ]);

        return response()->json([
            'wishlisted' => true,
            'message'    => 'Ditambahkan ke wishlist.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: format listing with isWishlisted flag
    // ─────────────────────────────────────────────────────────────
    private function formatListing(MarketplaceListing $listing, array $wishlistedIds): array
    {
        return [
            'id'            => (string) $listing->id,
            'name'          => $listing->name,
            'price'         => $listing->price,
            'seller_name'   => $listing->seller_name,
            'seller_rating' => $listing->seller_rating,
            'description'   => $listing->description,
            'category'      => $listing->category,
            'condition'     => $listing->condition,
            'image_url'     => $listing->image_path
                ? asset('storage/' . $listing->image_path)
                : null,
            'is_wishlisted' => in_array($listing->id, $wishlistedIds),
            'is_sold'       => $listing->is_sold,
        ];
    }
}
