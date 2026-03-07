<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Courier;
use App\Models\PickupRequest;
use App\Models\WasteCategory;
use Illuminate\Support\Facades\DB;

echo "=== Test: simulate assignCourier on pickup #2 ===\n";

$pickup = PickupRequest::find(2);
if (!$pickup) { echo "Pickup #2 not found\n"; exit(1); }

echo "Before: courier_id=" . ($pickup->courier_id ?? 'NULL') . "\n";

// --- replicate selectBestCourier ---
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

if (!$courier) {
    echo "No courier available!\n";
    exit(1);
}

echo "Selected courier: [{$courier->id}] {$courier->name}\n";

DB::transaction(function () use ($pickup, $courier) {
    $pickup->update(['courier_id' => $courier->id]);
    $activeCount = $courier->activePickups()->count();
    if ($activeCount >= 1) {
        $courier->update(['status' => 'on_duty']);
    }
    $courier->increment('total_deliveries');
});

$pickup->refresh();
echo "After: courier_id=" . ($pickup->courier_id ?? 'NULL') . "\n";
echo "Courier status: " . Courier::find($courier->id)->status . "\n";
echo "OK\n";
