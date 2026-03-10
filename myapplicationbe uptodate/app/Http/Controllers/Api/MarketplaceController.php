<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/marketplace
    // Query params: category (optional), search (optional)
    // Returns paginated active listings with wishlisted flag per user
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $category = $request->query('category');
        $search   = $request->query('search');

        // Get user's wishlist listing IDs for isWishlisted flag
        $wishlistedIds = $user->wishlists()->pluck('listing_id')->toArray();

        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = MarketplaceListing::active()
            ->byCategory($category)
            ->when($search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate($perPage);

        $listings = $paginator->getCollection()->map(
            fn (MarketplaceListing $listing) => $this->formatListing($listing, $wishlistedIds)
        );

        return response()->json([
            'data' => $listings,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/marketplace/{id}
    // Returns single listing detail with isWishlisted flag
    // ─────────────────────────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $listing = MarketplaceListing::active()->findOrFail($id);

        // Increment view count
        $listing->increment('views_count');

        $wishlistedIds = $request->user()->wishlists()->pluck('listing_id')->toArray();

        return response()->json(['data' => $this->formatListing($listing, $wishlistedIds)]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/marketplace
    // Create a new listing (seller creates product)
    // Body: { name, description, price, category, condition, seller_name? }
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'price'       => ['required', 'integer', 'min:1000'],
            'category'    => ['required', 'in:furniture,electronics,clothing,books,others'],
            'condition'   => ['required', 'in:like_new,good,fair'],
        ]);

        $listing = MarketplaceListing::create([
            'seller_id'    => $user->id,
            'seller_name'  => $user->name,
            'seller_rating' => 5.0,
            ...$validated,
        ]);

        $user->increment('items_sold');

        return response()->json([
            'message' => 'Listing berhasil dibuat.',
            'data'    => $this->formatListing($listing, []),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/marketplace/mine
    // Returns all listings belonging to the authenticated seller
    // ─────────────────────────────────────────────────────────────
    public function myListings(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = $request->user()
            ->marketplaceListings()
            ->latest()
            ->paginate($perPage);

        $listings = $paginator->getCollection()->map(
            fn (MarketplaceListing $listing) => $this->formatListing($listing, [])
        );

        return response()->json([
            'data' => $listings,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PUT /api/marketplace/{id}
    // Update a listing owned by the authenticated seller
    // Body (all optional): { name, description, price, category, condition }
    // ─────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $listing = $request->user()
            ->marketplaceListings()
            ->findOrFail($id);

        if ($listing->is_sold) {
            return response()->json([
                'message' => 'Barang yang sudah terjual tidak bisa diedit.',
            ], 422);
        }

        $validated = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:2000'],
            'price'       => ['sometimes', 'integer', 'min:1000'],
            'category'    => ['sometimes', 'in:furniture,electronics,clothing,books,others'],
            'condition'   => ['sometimes', 'in:like_new,good,fair'],
        ]);

        $listing->update($validated);

        return response()->json([
            'message' => 'Listing berhasil diperbarui.',
            'data'    => $this->formatListing($listing->fresh(), []),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE /api/marketplace/{id}
    // Soft-delete (set is_active = false) a listing owned by the user
    // ─────────────────────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $listing = $request->user()
            ->marketplaceListings()
            ->findOrFail($id);

        if ($listing->is_sold) {
            return response()->json([
                'message' => 'Barang yang sudah terjual tidak bisa dihapus.',
            ], 422);
        }

        $listing->update(['is_active' => false]);

        return response()->json(['message' => 'Listing berhasil dihapus.']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: format listing into consistent array shape
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
            'views_count'   => $listing->views_count,
            'created_at'    => $listing->created_at?->toDateTimeString(),
        ];
    }
}
