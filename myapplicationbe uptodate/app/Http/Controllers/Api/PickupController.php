<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PickupRequest;
use App\Models\WasteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PickupController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/pickups
    // Returns all pickup requests for the authenticated user,
    // newest first, with their items and waste categories.
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $pickups = $request->user()
            ->pickupRequests()
            ->with(['items.wasteCategory', 'courier'])
            ->latest()
            ->get()
            ->map(fn ($pickup) => $this->formatPickup($pickup));

        return response()->json([
            'data' => $pickups,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/pickups
    // Body: {
    //   address, latitude?, longitude?,
    //   pickup_date (Y-m-d), pickup_time (HH:MM),
    //   notes?,
    //   trash_types: ["organic","plastic","electronic","glass"]
    // }
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validTrashTypes = ['organic', 'plastic', 'electronic', 'glass'];

        $validated = $request->validate([
            'address'               => ['required', 'string', 'max:500'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'pickup_date'           => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'pickup_time'           => ['required', 'date_format:H:i'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'estimated_weight_kg'   => ['nullable', 'numeric', 'min:0.1', 'max:9999'],
            'trash_types'           => ['required', 'array', 'min:1'],
            'trash_types.*'         => ['required', 'string', Rule::in($validTrashTypes)],
        ]);

        // Resolve waste category IDs from the provided trash_types slugs
        $categories = WasteCategory::whereIn('type', $validated['trash_types'])
            ->where('is_active', true)
            ->get()
            ->keyBy('type');

        // Ensure every requested type actually exists in the DB
        $missing = collect($validated['trash_types'])
            ->diff($categories->keys())
            ->values();

        if ($missing->isNotEmpty()) {
            return response()->json([
                'message' => 'Kategori sampah tidak ditemukan: ' . $missing->implode(', '),
                'errors'  => ['trash_types' => ["Kategori tidak valid: {$missing->implode(', ')}"]],
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create the pickup request — starts in 'searching' until a courier accepts
            $pickup = PickupRequest::create([
                'user_id'               => $request->user()->id,
                'address'               => $validated['address'],
                'latitude'              => $validated['latitude'] ?? null,
                'longitude'             => $validated['longitude'] ?? null,
                'pickup_date'           => $validated['pickup_date'],
                'pickup_time'           => $validated['pickup_time'],
                'status'                => 'searching',
                'notes'                 => $validated['notes'] ?? null,
                'estimated_weight_kg'   => $validated['estimated_weight_kg'] ?? null,
            ]);

            // Create one pickup_item row per trash type
            foreach ($validated['trash_types'] as $type) {
                $pickup->items()->create([
                    'waste_category_id' => $categories[$type]->id,
                ]);
            }

            // Increment user's total_pickups counter
            $request->user()->increment('total_pickups');

            DB::commit();

            // Return the freshly created pickup with all relations
            $pickup->load(['items.wasteCategory', 'courier']);

            return response()->json([
                'message' => 'Pickup berhasil dijadwalkan! Mencari kurir...',
                'data'    => $this->formatPickup($pickup),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal membuat pickup request. Silakan coba lagi.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/pickups/{id}
    // Returns a single pickup request (must belong to the auth user)
    // ─────────────────────────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $pickup = $request->user()
            ->pickupRequests()
            ->with(['items.wasteCategory', 'courier'])
            ->findOrFail($id);

        return response()->json([
            'data' => $this->formatPickup($pickup),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE /api/pickups/{id}/cancel
    // Cancel a pending pickup (only owner can cancel)
    // ─────────────────────────────────────────────────────────────
    public function cancel(Request $request, int $id): JsonResponse
    {
        $pickup = $request->user()
            ->pickupRequests()
            ->findOrFail($id);

        if (! in_array($pickup->status, ['searching', 'pending'])) {
            return response()->json([
                'message' => 'Hanya pickup berstatus searching atau pending yang bisa dibatalkan.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $pickup->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Pickup berhasil dibatalkan.',
            'data'    => $this->formatPickup($pickup->fresh(['items.wasteCategory', 'courier'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Private helper — normalise a PickupRequest for the API response
    // ─────────────────────────────────────────────────────────────
    private function formatPickup(PickupRequest $pickup): array
    {
        return [
            'id'           => $pickup->id,
            'status'       => $pickup->status,
            'address'      => $pickup->address,
            'latitude'     => $pickup->latitude,
            'longitude'    => $pickup->longitude,
            'pickup_date'  => $pickup->pickup_date?->format('Y-m-d'),
            'pickup_time'  => $pickup->pickup_time,
            'notes'        => $pickup->notes,
            'points_awarded'      => $pickup->points_awarded,
            'estimated_weight_kg' => $pickup->estimated_weight_kg,
            'completed_at'        => $pickup->completed_at?->toIso8601String(),
            'cancelled_at'        => $pickup->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $pickup->cancellation_reason,
            'created_at'   => $pickup->created_at?->toIso8601String(),
            'courier'      => $pickup->courier ? [
                'id'            => $pickup->courier->id,
                'name'          => $pickup->courier->name,
                'phone'         => $pickup->courier->phone,
                'avatar_path'   => $pickup->courier->avatar_path,
                'vehicle_type'  => $pickup->courier->vehicle_type,
                'vehicle_plate' => $pickup->courier->vehicle_plate,
                'rating'        => $pickup->courier->rating,
                'status'        => $pickup->courier->status,
            ] : null,
            'trash_types'  => $pickup->items->map(fn ($item) => [
                'id'       => $item->wasteCategory->id,
                'type'     => $item->wasteCategory->type,
                'label'    => $item->wasteCategory->label,
                'emoji'    => $item->wasteCategory->emoji,
                'estimated_weight_kg' => $item->estimated_weight_kg,
            ])->values()->toArray(),
        ];
    }
}
