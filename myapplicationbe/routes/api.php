<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CartCheckoutController;
use App\Http\Controllers\Api\CourierController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PickupController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────
// Public auth routes (no token required)
// ─────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/google',   [AuthController::class, 'googleLogin']);
});

// ─────────────────────────────────────────────────────────────────
// Protected routes (Sanctum Bearer token required)
// ─────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });

    // Pickup routes
    // GET  /api/pickups            → list user's pickups
    // POST /api/pickups            → create pickup
    // GET  /api/pickups/{id}       → show pickup
    // POST /api/pickups/{id}/cancel → cancel pickup
    Route::prefix('pickups')->group(function () {
        Route::get('/',                 [PickupController::class, 'index']);
        Route::post('/',                [PickupController::class, 'store']);
        Route::get('/{id}',             [PickupController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/cancel',     [PickupController::class, 'cancel'])->whereNumber('id');
    });

    // Marketplace routes
    // GET  /api/marketplace         → list active listings (with ?category= & ?search=)
    // POST /api/marketplace         → create a new listing (seller)
    // GET  /api/marketplace/{id}    → listing detail
    Route::prefix('marketplace')->group(function () {
        Route::get('/mine',         [MarketplaceController::class, 'myListings']);
        Route::get('/',             [MarketplaceController::class, 'index']);
        Route::post('/',            [MarketplaceController::class, 'store']);
        Route::get('/{id}',         [MarketplaceController::class, 'show'])->whereNumber('id');
        Route::put('/{id}',         [MarketplaceController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}',      [MarketplaceController::class, 'destroy'])->whereNumber('id');
    });

    // Order routes
    Route::prefix('orders')->group(function () {
        Route::get('/sales-transactions',                          [OrderController::class, 'salesTransactions']);
        // ── Cart checkout (multi-item, 1 Mayar payment) ─────────
        Route::post('/checkout-cart',                              [CartCheckoutController::class, 'checkout']);
        Route::get('/cart-checkouts',                              [CartCheckoutController::class, 'myCartCheckouts']);
        Route::get('/cart-checkout/{cartCheckoutId}/payment-status', [CartCheckoutController::class, 'paymentStatus']);
        Route::post('/cart-checkout/{cartCheckoutId}/cancel',      [CartCheckoutController::class, 'cancel']);
        // ── Single order routes ──────────────────────────────────
        Route::get('/',                        [OrderController::class, 'index']);
        Route::post('/',                       [OrderController::class, 'store']);
        Route::get('/{id}',                    [OrderController::class, 'show'])->whereNumber('id');
        Route::post('/{id}/pay',               [OrderController::class, 'pay'])->whereNumber('id');
        Route::get('/{id}/payment-status',     [OrderController::class, 'paymentStatus'])->whereNumber('id');
        Route::post('/{id}/cancel',            [OrderController::class, 'cancel'])->whereNumber('id');
    });    // Wishlist routes
    // GET  /api/wishlist         → list user's wishlisted items
    // POST /api/wishlist/toggle  → toggle wishlist (add or remove)
    Route::prefix('wishlist')->group(function () {
        Route::get('/',       [WishlistController::class, 'index']);
        Route::post('/toggle', [WishlistController::class, 'toggle']);
    });

    // Address routes
    // GET    /api/addresses              → list user's addresses
    // POST   /api/addresses              → add new address
    // PATCH  /api/addresses/{id}/default → set as default
    // DELETE /api/addresses/{id}         → delete address
    Route::prefix('addresses')->group(function () {
        Route::get('/',               [AddressController::class, 'index']);
        Route::post('/',              [AddressController::class, 'store']);
        Route::patch('/{id}/default', [AddressController::class, 'setDefault'])->whereNumber('id');
        Route::delete('/{id}',        [AddressController::class, 'destroy'])->whereNumber('id');
    });
});

// ─────────────────────────────────────────────────────────────────
// Courier-protected routes (Sanctum token issued to a Courier model)
// GET  /api/courier/me                      → courier profile
// GET  /api/courier/pickups                 → assigned pickups
// PATCH /api/courier/pickups/{id}/status   → update pickup status
// PATCH /api/courier/availability          → toggle online/offline
// ─────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureIsCourier::class])
    ->prefix('courier')
    ->group(function () {
        Route::get('/me',                              [CourierController::class, 'me']);
        Route::get('/available-pickups',               [CourierController::class, 'availablePickups']);
        Route::post('/pickups/{id}/accept',            [CourierController::class, 'acceptPickup'])->whereNumber('id');
        Route::get('/pickups',                         [CourierController::class, 'pickups']);
        Route::patch('/pickups/{id}/status',           [CourierController::class, 'updateStatus'])->whereNumber('id');
        Route::patch('/availability',                  [CourierController::class, 'availability']);
    });
