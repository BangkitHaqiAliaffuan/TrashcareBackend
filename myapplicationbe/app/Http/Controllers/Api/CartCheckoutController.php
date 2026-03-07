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
use Illuminate\Support\Str;

class CartCheckoutController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // POST /api/orders/checkout-cart
    // Checkout multiple items from cart in ONE payment (Mayar)
    //
    // Body:
    // {
    //   "shipping_address": "...",
    //   "notes": "...",          // optional, applied to all orders
    //   "items": [
    //     { "listing_id": 1, "quantity": 2 },
    //     { "listing_id": 5, "quantity": 1 }
    //   ]
    // }
    //
    // Returns:
    // {
    //   "cart_checkout_id": "cc_...",
    //   "payment_link": "https://...",
    //   "payment_id": "...",
    //   "total": 150000,
    //   "orders": [ ...formatOrder() ]
    // }
    // ─────────────────────────────────────────────────────────────
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shipping_address'       => ['required', 'string', 'max:500'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'items'                  => ['required', 'array', 'min:1', 'max:20'],
            'items.*.listing_id'     => ['required', 'integer', 'exists:marketplace_listings,id'],
            'items.*.quantity'       => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $buyer   = $request->user();
        $cartId  = 'cc_' . Str::uuid();
        $total   = 0;
        $createdOrders = [];

        // --- Validate all listings first ---
        $listings = [];
        foreach ($validated['items'] as $item) {
            $listing = MarketplaceListing::active()->find($item['listing_id']);
            if (!$listing) {
                return response()->json([
                    'message' => "Produk ID {$item['listing_id']} tidak tersedia atau sudah terjual.",
                ], 422);
            }
            if ($listing->seller_id === $buyer->id) {
                return response()->json([
                    'message' => "Kamu tidak bisa membeli barangmu sendiri ({$listing->name}).",
                ], 422);
            }
            $listings[$item['listing_id']] = [
                'listing'  => $listing,
                'quantity' => $item['quantity'] ?? 1,
            ];
            // Check stock
            $qty = $item['quantity'] ?? 1;
            if ($listing->stock < $qty) {
                return response()->json([
                    'message' => "Stok {$listing->name} tidak mencukupi. Tersedia: {$listing->stock}.",
                ], 422);
            }
            $total += $listing->price * $qty;
        }

        DB::beginTransaction();
        try {
            foreach ($listings as $entry) {
                $listing  = $entry['listing'];
                $quantity = $entry['quantity'];

                $order = Order::create([
                    'buyer_id'         => $buyer->id,
                    'listing_id'       => $listing->id,
                    'status'           => 'pending',
                    'total_price'      => $listing->price * $quantity,
                    'quantity'         => $quantity,
                    'notes'            => $validated['notes'] ?? null,
                    'cart_checkout_id' => $cartId,
                    'shipping_address' => $validated['shipping_address'],
                    'payment_status'   => 'unpaid',
                ]);

                $listing->update([
                    'stock'     => max(0, $listing->stock - $quantity),
                    'is_sold'   => ($listing->stock - $quantity) <= 0,
                    'is_active' => ($listing->stock - $quantity) > 0,
                ]);

                $order->load('listing');
                $createdOrders[] = $order;
            }

            // --- Create ONE Mayar payment for total of all items ---
            $itemsPayload = collect($listings)->map(function ($entry) {
                $listing  = $entry['listing'];
                $quantity = $entry['quantity'];
                return [
                    'name'        => $listing->name,
                    'description' => $listing->description ?? $listing->name,
                    'quantity'    => $quantity,
                    'rate'        => (int) $listing->price,
                ];
            })->values()->toArray();

            $mayar  = new MayarService();
            $result = $mayar->createPayment([
                'name'        => $buyer->name,
                'email'       => $buyer->email,
                'amount'      => $total,
                'mobile'      => $buyer->phone ?? '',
                'description' => 'Cart Checkout ' . $cartId . ' — ' . count($createdOrders) . ' produk',
                'redirectUrl' => config('app.url') . '/payment/callback',
                'expiredAt'   => now()->addHours(24)->toIso8601String(),
                'items'       => $itemsPayload,
            ]);

            // Update all orders with the same payment id/link
            Order::where('cart_checkout_id', $cartId)->update([
                'mayar_payment_id'   => $result['id'],
                'mayar_payment_link' => $result['link'],
            ]);

            DB::commit();

            // Reload fresh orders after update
            $freshOrders = Order::with('listing')
                ->where('cart_checkout_id', $cartId)
                ->get();

            return response()->json([
                'message'          => 'Checkout berhasil! Silakan selesaikan pembayaran.',
                'cart_checkout_id' => $cartId,
                'payment_link'     => $result['link'],
                'payment_id'       => $result['id'],
                'total'            => $total,
                'orders'           => $freshOrders->map(fn($o) => $this->formatOrder($o))->values(),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Checkout gagal: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/orders/cart-checkout/{cartCheckoutId}/payment-status
    // Poll Mayar payment status for a whole cart checkout group.
    // If paid → confirm all orders in the group.
    // ─────────────────────────────────────────────────────────────
    public function paymentStatus(Request $request, string $cartCheckoutId): JsonResponse
    {
        $orders = Order::with('listing')
            ->where('cart_checkout_id', $cartCheckoutId)
            ->where('buyer_id', $request->user()->id)
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Cart checkout tidak ditemukan.'], 404);
        }

        $firstOrder = $orders->first();
        $mayarId    = $firstOrder->mayar_payment_id;

        if (!$mayarId) {
            return response()->json([
                'payment_status' => 'unpaid',
                'order_status'   => $firstOrder->status,
                'orders'         => $orders->map(fn($o) => $this->formatOrder($o))->values(),
            ]);
        }

        // If already confirmed/beyond pending, no need to call Mayar again
        if (!$firstOrder->isPending()) {
            return response()->json([
                'payment_status' => $firstOrder->payment_status,
                'order_status'   => $firstOrder->status,
                'orders'         => $orders->map(fn($o) => $this->formatOrder($o))->values(),
            ]);
        }

        try {
            $mayar  = new MayarService();
            $detail = $mayar->getPaymentDetail($mayarId);
            $status = $detail['status'] ?? 'unpaid';

            if ($status === 'paid') {
                $now = now();
                Order::where('cart_checkout_id', $cartCheckoutId)->update([
                    'payment_status' => 'paid',
                    'paid_at'        => $now,
                    'status'         => 'confirmed',
                    'confirmed_at'   => $now,
                ]);

                // Dispatch auto-complete for each order
                $orders->each(function ($o) {
                    AutoCompleteOrder::dispatch($o->fresh())->delay(now()->addMinutes(2));
                });

                $orders = Order::with('listing')
                    ->where('cart_checkout_id', $cartCheckoutId)
                    ->where('buyer_id', $request->user()->id)
                    ->get();
            }

            return response()->json([
                'payment_status' => $orders->first()->payment_status,
                'order_status'   => $orders->first()->status,
                'orders'         => $orders->map(fn($o) => $this->formatOrder($o))->values(),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'payment_status' => $firstOrder->payment_status,
                'order_status'   => $firstOrder->status,
                'orders'         => $orders->map(fn($o) => $this->formatOrder($o))->values(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/orders/cart-checkout/{cartCheckoutId}/cancel
    // Cancel all pending orders in a cart checkout group + close Mayar
    // ─────────────────────────────────────────────────────────────
    public function cancel(Request $request, string $cartCheckoutId): JsonResponse
    {
        $orders = Order::with('listing')
            ->where('cart_checkout_id', $cartCheckoutId)
            ->where('buyer_id', $request->user()->id)
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Cart checkout tidak ditemukan.'], 404);
        }

        $firstOrder = $orders->first();
        if (!$firstOrder->isPending()) {
            return response()->json(['message' => 'Hanya pesanan pending yang bisa dibatalkan.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            // Close Mayar payment (shared payment_id)
            if ($firstOrder->mayar_payment_id) {
                try {
                    (new MayarService())->closePayment($firstOrder->mayar_payment_id);
                } catch (\Throwable) {}
            }

            $now = now();
            Order::where('cart_checkout_id', $cartCheckoutId)->update([
                'status'              => 'cancelled',
                'payment_status'      => 'closed',
                'cancelled_at'        => $now,
                'cancellation_reason' => $validated['reason'] ?? null,
            ]);

            // Restore stock for each listing
            $orders->each(function ($o) {
                $listing = $o->listing;
                if ($listing) {
                    $restoredStock = $listing->stock + ($o->quantity ?? 1);
                    $listing->update([
                        'stock'     => $restoredStock,
                        'is_sold'   => false,
                        'is_active' => true,
                    ]);
                }
            });

            DB::commit();

            $fresh = Order::with('listing')
                ->where('cart_checkout_id', $cartCheckoutId)
                ->get();

            return response()->json([
                'message' => 'Semua pesanan dalam checkout ini berhasil dibatalkan.',
                'orders'  => $fresh->map(fn($o) => $this->formatOrder($o))->values(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal membatalkan: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/orders/cart-checkouts
    // Returns all distinct cart checkout groups for the buyer,
    // each with the list of orders in it.
    // ─────────────────────────────────────────────────────────────
    public function myCartCheckouts(Request $request): JsonResponse
    {
        $buyer = $request->user();

        $orders = Order::with('listing')
            ->where('buyer_id', $buyer->id)
            ->whereNotNull('cart_checkout_id')
            ->latest()
            ->get();

        // Group by cart_checkout_id
        $groups = $orders->groupBy('cart_checkout_id')->map(function ($groupOrders) {
            $first = $groupOrders->first();
            return [
                'cart_checkout_id' => $first->cart_checkout_id,
                'payment_link'     => $first->mayar_payment_link,
                'payment_id'       => $first->mayar_payment_id,
                'payment_status'   => $first->payment_status,
                'order_status'     => $first->status,
                'total'            => $groupOrders->sum('total_price'),
                'shipping_address' => $first->shipping_address,
                'ordered_at'       => $first->created_at?->format('d M Y'),
                'orders'           => $groupOrders->map(fn($o) => $this->formatOrder($o))->values(),
            ];
        })->values();

        return response()->json(['data' => $groups]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper: format order shape (mirrors OrderController::formatOrder)
    // ─────────────────────────────────────────────────────────────
    private function formatOrder(Order $order): array
    {
        $listing = $order->listing;
        return [
            'id'                 => (string) $order->id,
            'cart_checkout_id'   => $order->cart_checkout_id,
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
