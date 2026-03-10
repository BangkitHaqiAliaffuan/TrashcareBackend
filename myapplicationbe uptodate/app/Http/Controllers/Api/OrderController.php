<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AutoCompleteOrder;
use App\Models\MarketplaceListing;
use App\Models\Order;
use App\Services\MayarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // GET /api/orders
    // Returns all orders for the authenticated buyer, newest first
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 50);

        $paginator = $request->user()
            ->orders()
            ->with('listing')
            ->latest()
            ->paginate($perPage);

        $orders = $paginator->getCollection()->map(fn ($order) => $this->formatOrder($order));

        return response()->json([
            'data' => $orders,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/orders
    // Create an order (buy a listing)
    // Body: { listing_id, quantity?, notes?, shipping_address }
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'listing_id'       => ['required', 'integer', 'exists:marketplace_listings,id'],
            'quantity'         => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'shipping_address' => ['required', 'string', 'max:500'],
        ]);

        $listing = MarketplaceListing::active()->findOrFail($validated['listing_id']);

        if ($listing->seller_id === $request->user()->id) {
            return response()->json([
                'message' => 'Kamu tidak bisa membeli barangmu sendiri.',
            ], 422);
        }

        $quantity = $validated['quantity'] ?? 1;

        DB::beginTransaction();
        try {
            $order = Order::create([
                'buyer_id'         => $request->user()->id,
                'listing_id'       => $listing->id,
                'status'           => 'pending',
                'total_price'      => $listing->price * $quantity,
                'quantity'         => $quantity,
                'notes'            => $validated['notes'] ?? null,
                'shipping_address' => $validated['shipping_address'],
                'payment_status'   => 'unpaid',
            ]);

            $listing->update(['is_sold' => true]);

            DB::commit();

            $order->load('listing');

            return response()->json([
                'message' => 'Pesanan berhasil dibuat.',
                'data'    => $this->formatOrder($order),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan. Silakan coba lagi.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/orders/{id}
    // Returns a single order (buyer only)
    // ─────────────────────────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $request->user()
            ->orders()
            ->with('listing')
            ->findOrFail($id);

        return response()->json(['data' => $this->formatOrder($order)]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/orders/{id}/pay
    // Creates a Mayar payment request and returns the payment link.
    // Android opens this link in a Chrome Custom Tab.
    // ─────────────────────────────────────────────────────────────
    public function pay(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->with('listing')->findOrFail($id);

        if (!$order->isPending()) {
            return response()->json([
                'message' => 'Hanya pesanan dengan status pending yang bisa dibayar.',
            ], 422);
        }

        // If payment link already created, return existing link
        if ($order->mayar_payment_id && $order->payment_status === 'unpaid') {
            return response()->json([
                'message'      => 'Link pembayaran sudah tersedia.',
                'payment_link' => $order->mayar_payment_link,
                'payment_id'   => $order->mayar_payment_id,
                'data'         => $this->formatOrder($order),
            ]);
        }

        $user = $request->user();

        try {
            $mayar   = new MayarService();
            $result  = $mayar->createPayment([
                'name'        => $user->name,
                'email'       => $user->email,
                'amount'      => $order->total_price,
                'mobile'      => $user->phone ?? '',
                'description' => 'Pembelian #' . $order->id . ' - ' . ($order->listing->name ?? 'Produk'),
                'redirectUrl' => config('app.url') . '/payment/callback',
                'expiredAt'   => now()->addHours(24)->toIso8601String(),
            ]);

            $order->update([
                'mayar_payment_id'   => $result['id'],
                'mayar_payment_link' => $result['link'],
                'payment_status'     => 'unpaid',
            ]);

            $order->refresh()->load('listing');

            return response()->json([
                'message'      => 'Link pembayaran berhasil dibuat.',
                'payment_link' => $result['link'],
                'payment_id'   => $result['id'],
                'data'         => $this->formatOrder($order),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal membuat payment request: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/orders/{id}/payment-status
    // Android polls this every 3 seconds to check if user has paid.
    // If Mayar returns status=paid → update order to confirmed.
    // ─────────────────────────────────────────────────────────────
    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->with('listing')->findOrFail($id);

        // Already confirmed / beyond pending → just return current state
        if (!$order->isPending()) {
            return response()->json([
                'payment_status' => $order->payment_status,
                'order_status'   => $order->status,
                'data'           => $this->formatOrder($order),
            ]);
        }

        if (!$order->mayar_payment_id) {
            return response()->json([
                'payment_status' => 'unpaid',
                'order_status'   => $order->status,
                'data'           => $this->formatOrder($order),
            ]);
        }

        try {
            $mayar  = new MayarService();
            $detail = $mayar->getPaymentDetail($order->mayar_payment_id);
            $status = $detail['status'] ?? 'unpaid';

            if ($status === 'paid' && $order->payment_status !== 'paid') {
                // Payment confirmed by Mayar → update order
                $order->update([
                    'payment_status' => 'paid',
                    'paid_at'        => now(),
                    'status'         => 'confirmed',
                    'confirmed_at'   => now(),
                ]);

                // Auto-complete after processing time
                AutoCompleteOrder::dispatch($order)->delay(now()->addMinutes(2));

                $order->refresh()->load('listing');
            }

            return response()->json([
                'payment_status' => $order->payment_status,
                'order_status'   => $order->status,
                'data'           => $this->formatOrder($order),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'payment_status' => $order->payment_status,
                'order_status'   => $order->status,
                'data'           => $this->formatOrder($order),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/orders/{id}/cancel
    // Cancel a pending order + close Mayar payment request
    // Body: { reason? }
    // ─────────────────────────────────────────────────────────────
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);

        if (!$order->isPending()) {
            return response()->json([
                'message' => 'Hanya pesanan dengan status pending yang bisa dibatalkan.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            // Close Mayar payment request if exists
            if ($order->mayar_payment_id) {
                try {
                    (new MayarService())->closePayment($order->mayar_payment_id);
                } catch (\Throwable) {
                    // Don't block cancellation if Mayar call fails
                }
            }

            $order->update([
                'status'              => 'cancelled',
                'payment_status'      => 'closed',
                'cancelled_at'        => now(),
                'cancellation_reason' => $validated['reason'] ?? null,
            ]);

            $order->listing()->update(['is_sold' => false]);

            DB::commit();

            $order->load('listing');

            return response()->json([
                'message' => 'Pesanan berhasil dibatalkan.',
                'data'    => $this->formatOrder($order),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan. Silakan coba lagi.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: format order into consistent array shape
    // ─────────────────────────────────────────────────────────────
    private function formatOrder(Order $order): array
    {
        $listing = $order->listing;

        return [
            'id'                 => (string) $order->id,
            'status'             => $order->status,
            'payment_status'     => $order->payment_status ?? 'unpaid',
            'mayar_payment_link' => $order->mayar_payment_link,
            'mayar_payment_id'   => $order->mayar_payment_id,
            'total_price'        => $order->total_price,
            'quantity'           => $order->quantity,
            'notes'              => $order->notes,
            'shipping_address'   => $order->shipping_address,
            'cancellation_reason'=> $order->cancellation_reason,
            'ordered_at'         => $order->created_at?->format('d M Y'),
            'paid_at'            => $order->paid_at?->format('d M Y'),
            'confirmed_at'       => $order->confirmed_at?->format('d M Y'),
            'shipped_at'         => $order->shipped_at?->format('d M Y'),
            'completed_at'       => $order->completed_at?->format('d M Y'),
            'cancelled_at'       => $order->cancelled_at?->format('d M Y'),
            'estimated_arrival'  => $order->shipped_at
                ? $order->shipped_at->addDays(3)->format('d M Y')
                : null,
            'listing' => $listing ? [
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
                'is_wishlisted' => false,
                'is_sold'       => $listing->is_sold,
            ] : null,
        ];
    }
}
