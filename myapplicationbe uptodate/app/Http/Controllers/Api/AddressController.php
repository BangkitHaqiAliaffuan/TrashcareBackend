<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/addresses
    // Returns all addresses belonging to the authenticated user
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->latest()
            ->get()
            ->map(fn (UserAddress $a) => $this->formatAddress($a));

        return response()->json(['data' => $addresses]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/addresses
    // Body: { label, recipient_name, phone, full_address, city, province, postal_code, is_default? }
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label'          => ['required', 'string', 'max:100'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone'          => ['required', 'string', 'max:20'],
            'full_address'   => ['required', 'string', 'max:1000'],
            'city'           => ['required', 'string', 'max:100'],
            'province'       => ['required', 'string', 'max:100'],
            'postal_code'    => ['required', 'string', 'max:10'],
            'is_default'     => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        // If this is the user's first address, make it default automatically
        $isFirst = $user->addresses()->count() === 0;

        // If new address is set as default, unset previous default
        if (!empty($validated['is_default']) || $isFirst) {
            $user->addresses()->update(['is_default' => false]);
            $validated['is_default'] = true;
        }

        $address = $user->addresses()->create($validated);

        return response()->json([
            'message' => 'Alamat berhasil ditambahkan.',
            'data'    => $this->formatAddress($address),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────
    // PATCH /api/addresses/{id}/set-default
    // Mark a specific address as the default
    // ─────────────────────────────────────────────────────────────
    public function setDefault(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        // Unset all defaults, then set the chosen one
        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Alamat utama berhasil diperbarui.',
            'data'    => $this->formatAddress($address->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // DELETE /api/addresses/{id}
    // Delete an address owned by the authenticated user
    // ─────────────────────────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $address = $user->addresses()->findOrFail($id);

        $wasDefault = $address->is_default;
        $address->delete();

        // Auto-promote newest remaining address to default if deleted one was default
        if ($wasDefault) {
            $next = $user->addresses()->latest()->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Alamat berhasil dihapus.']);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: format address into consistent array shape
    // ─────────────────────────────────────────────────────────────
    private function formatAddress(UserAddress $address): array
    {
        return [
            'id'             => (string) $address->id,
            'label'          => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone'          => $address->phone,
            'full_address'   => $address->full_address,
            'city'           => $address->city,
            'province'       => $address->province,
            'postal_code'    => $address->postal_code,
            'is_default'     => $address->is_default,
            'created_at'     => $address->created_at?->toDateTimeString(),
        ];
    }
}
