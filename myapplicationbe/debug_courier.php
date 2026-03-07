<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Courier;
use App\Models\PickupRequest;

echo "=== Couriers ===\n";
Courier::all(['id','name','status','is_available','rating'])->each(function ($c) {
    echo "  [{$c->id}] {$c->name} | status={$c->status} | available={$c->is_available} | rating={$c->rating}\n";
});

echo "\n=== selectBestCourier query ===\n";
$courier = Courier::query()
    ->where('status', '!=', 'inactive')
    ->where('is_available', true)
    ->withCount([
        'pickupRequests as active_pickups_count' => function ($q) {
            $q->whereIn('status', ['pending', 'on_the_way']);
        },
    ])
    ->orderBy('active_pickups_count', 'asc')
    ->orderBy('rating', 'desc')
    ->first();

if ($courier) {
    echo "  Best courier: [{$courier->id}] {$courier->name} | active_pickups={$courier->active_pickups_count}\n";
} else {
    echo "  No courier found!\n";
}

echo "\n=== Recent pickup_requests (last 5) ===\n";
PickupRequest::latest()->take(5)->get(['id','status','courier_id','created_at'])->each(function ($p) {
    echo "  Pickup #{$p->id} | status={$p->status} | courier_id=" . ($p->courier_id ?? 'NULL') . " | created={$p->created_at}\n";
});

echo "\n=== Laravel log (last 20 lines) ===\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last = array_slice($lines, -20);
    foreach ($last as $line) echo $line;
} else {
    echo "  No log file found.\n";
}
