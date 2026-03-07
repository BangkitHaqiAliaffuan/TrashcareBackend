<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\PickupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourierController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/courier/me
    // Returns the authenticated courier's profile.
    // ─────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        return response()->json([
            'courier' => $this->courierResource($courier),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/courier/available-pickups
    // Returns pickup requests in 'searching' state (no courier yet).
    // Any online courier can see and accept these.
    // ─────────────────────────────────────────────────────────────
    public function availablePickups(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        // Only show available pickups if the courier is online
        if (! $courier->is_available) {
            return response()->json(['data' => []]);
        }

        $pickups = PickupRequest::with(['items.wasteCategory', 'user'])
            ->whereNull('courier_id')
            ->where('status', 'searching')
            ->orderBy('created_at', 'asc')   // oldest request first
            ->get()
            ->map(fn ($p) => $this->formatPickup($p));

        return response()->json(['data' => $pickups]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/courier/pickups/{id}/accept
    // Courier accepts a 'searching' pickup → assigns self, status → pending
    // ─────────────────────────────────────────────────────────────
    public function acceptPickup(Request $request, int $id): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $pickup = PickupRequest::where('status', 'searching')
            ->whereNull('courier_id')
            ->findOrFail($id);

        DB::transaction(function () use ($pickup, $courier) {
            $pickup->update([
                'courier_id' => $courier->id,
                'status'     => 'pending',
            ]);

            // Mark courier as on_duty
            $courier->update(['status' => 'on_duty']);
            $courier->increment('total_deliveries');
        });

        $pickup->load(['items.wasteCategory', 'user']);

        return response()->json([
            'message' => 'Pickup berhasil diterima!',
            'data'    => $this->formatPickup($pickup),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/courier/pickups
    // Returns all pickup requests assigned to this courier.
    // ─────────────────────────────────────────────────────────────
    public function pickups(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $pickups = PickupRequest::with(['items.wasteCategory', 'user'])
            ->where('courier_id', $courier->id)
            ->orderByRaw("FIELD(status, 'on_the_way', 'pending', 'done', 'cancelled')")
            ->orderBy('pickup_date', 'asc')
            ->orderBy('pickup_time', 'asc')
            ->get()
            ->map(fn ($p) => $this->formatPickup($p));

        return response()->json(['data' => $pickups]);
    }

    // ─────────────────────────────────────────────────────────────
    // PATCH /api/courier/pickups/{id}/status
    // Body: { status: "on_the_way" | "done" }
    // Courier can only advance status forward; cannot cancel.
    // ─────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $pickup = PickupRequest::where('courier_id', $courier->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['on_the_way', 'done', 'cancelled'])],
        ]);

        $newStatus = $validated['status'];

        // Guard against going backwards (cancelled is always allowed from pending/on_the_way)
        $order = ['searching' => 0, 'pending' => 1, 'on_the_way' => 2, 'done' => 3, 'cancelled' => 4];
        $isAllowedForward = ($order[$newStatus] ?? 0) > ($order[$pickup->status] ?? 0);
        $isCancelFromActive = $newStatus === 'cancelled' && in_array($pickup->status, ['pending', 'on_the_way']);
        if (! $isAllowedForward && ! $isCancelFromActive) {
            return response()->json([
                'message' => "Tidak bisa mengubah status dari '{$pickup->status}' ke '{$newStatus}'.",
            ], 422);
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === 'done') {
            $updates['completed_at'] = now();
            $updates['points_awarded'] = 10; // base reward points for user

            // Award points to the customer
            $pickup->user()->increment('points_balance', $updates['points_awarded']);

            // Free up courier availability when all their pickups are done
            $remainingActive = PickupRequest::where('courier_id', $courier->id)
                ->whereIn('status', ['pending', 'on_the_way'])
                ->where('id', '!=', $pickup->id)
                ->count();

            if ($remainingActive === 0) {
                $courier->update(['status' => 'active', 'is_available' => true]);
            }
        }

        if ($newStatus === 'cancelled') {
            $updates['cancelled_at'] = now();
            $updates['courier_id']   = null; // unassign courier so pickup can be re-searched

            // Reset status back to searching so another courier can pick it up
            $updates['status'] = 'searching';

            // Free up courier if no more active pickups
            $remainingActive = PickupRequest::where('courier_id', $courier->id)
                ->whereIn('status', ['pending', 'on_the_way'])
                ->where('id', '!=', $pickup->id)
                ->count();

            if ($remainingActive === 0) {
                $courier->update(['status' => 'active', 'is_available' => true]);
            }
        }

        $pickup->update($updates);

        return response()->json([
            'message' => 'Status pickup berhasil diperbarui.',
            'data'    => $this->formatPickup($pickup->fresh(['items.wasteCategory', 'user'])),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PATCH /api/courier/availability
    // Body: { is_available: true | false }
    // Toggle courier availability (e.g. going offline)
    // ─────────────────────────────────────────────────────────────
    public function availability(Request $request): JsonResponse
    {
        /** @var Courier $courier */
        $courier = $request->user();

        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $courier->update(['is_available' => $validated['is_available']]);

        return response()->json([
            'message'      => $validated['is_available'] ? 'Kamu sekarang online.' : 'Kamu sekarang offline.',
            'is_available' => (bool) $courier->is_available,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    private function courierResource(Courier $courier): array
    {
        return [
            'id'               => $courier->id,
            'name'             => $courier->name,
            'email'            => $courier->email,
            'phone'            => $courier->phone,
            'avatar_path'      => $courier->avatar_path,
            'vehicle_type'     => $courier->vehicle_type,
            'vehicle_plate'    => $courier->vehicle_plate,
            'status'           => $courier->status,
            'is_available'     => (bool) $courier->is_available,
            'rating'           => (float) $courier->rating,
            'total_deliveries' => (int) $courier->total_deliveries,
        ];
    }

    private function formatPickup(PickupRequest $pickup): array
    {
        return [
            'id'                  => $pickup->id,
            'status'              => $pickup->status,
            'address'             => $pickup->address,
            'latitude'            => $pickup->latitude,
            'longitude'           => $pickup->longitude,
            'pickup_date'         => $pickup->pickup_date?->format('Y-m-d'),
            'pickup_time'         => $pickup->pickup_time,
            'notes'               => $pickup->notes,
            'estimated_weight_kg' => $pickup->estimated_weight_kg,
            'points_awarded'      => $pickup->points_awarded,
            'completed_at'        => $pickup->completed_at?->toIso8601String(),
            'cancelled_at'        => $pickup->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $pickup->cancellation_reason,
            'created_at'          => $pickup->created_at?->toIso8601String(),
            'customer'            => $pickup->user ? [
                'id'    => $pickup->user->id,
                'name'  => $pickup->user->name,
                'phone' => $pickup->user->phone,
            ] : null,
            'trash_types' => $pickup->items->map(fn ($item) => [
                'id'    => $item->wasteCategory->id,
                'type'  => $item->wasteCategory->type,
                'label' => $item->wasteCategory->label,
                'emoji' => $item->wasteCategory->emoji,
            ])->values()->toArray(),
        ];
    }
}
