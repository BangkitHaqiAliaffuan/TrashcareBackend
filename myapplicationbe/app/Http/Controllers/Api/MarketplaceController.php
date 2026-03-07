<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
    // Body: multipart/form-data — { name, description, price, category, condition, image? }
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
            'stock'       => ['nullable', 'integer', 'min:1', 'max:9999'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('listings', 'public');
        }

        $stock = $validated['stock'] ?? 1;

        $listing = MarketplaceListing::create([
            'seller_id'    => $user->id,
            'seller_name'  => $user->name,
            'seller_rating' => 5.0,
            'name'         => $validated['name'],
            'description'  => $validated['description'],
            'price'        => $validated['price'],
            'category'     => $validated['category'],
            'condition'    => $validated['condition'],
            'stock'        => $stock,
            'is_sold'      => false,
            'is_active'    => true,
            'image_path'   => $imagePath,
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
            'stock'       => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'image'       => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($listing->image_path) {
                Storage::disk('public')->delete($listing->image_path);
            }
            $validated['image_path'] = $request->file('image')->store('listings', 'public');
        }

        // When stock is set to 0 → auto mark as sold out
        if (isset($validated['stock'])) {
            if ($validated['stock'] === 0) {
                $validated['is_sold']   = true;
                $validated['is_active'] = false;
            } else {
                // Restoring stock → reactivate listing
                $validated['is_sold']   = false;
                $validated['is_active'] = true;
            }
        }

        unset($validated['image']); // remove file key before mass-assign
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
            'is_active'     => $listing->is_active,
            'stock'         => $listing->stock,
            'views_count'   => $listing->views_count,
            'created_at'    => $listing->created_at?->toDateTimeString(),
        ];
    }
}
