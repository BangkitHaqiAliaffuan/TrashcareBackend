<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches MarketplaceViewModel.addToCart() → buyer purchases a listing
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')
                ->constrained('marketplace_listings')
                ->restrictOnDelete(); // keep history even if listing soft-deleted

            $table->enum('status', [
                'pending',      // order placed, waiting seller confirmation
                'confirmed',    // seller confirmed
                'shipped',      // item on the way
                'completed',    // buyer received item
                'cancelled',    // cancelled by buyer or seller
            ])->default('pending');

            $table->unsignedBigInteger('total_price');   // snapshot of price at order time
            $table->integer('quantity')->default(1);      // for future bulk support
            $table->text('notes')->nullable();            // buyer notes to seller
            $table->string('shipping_address');           // delivery address

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
