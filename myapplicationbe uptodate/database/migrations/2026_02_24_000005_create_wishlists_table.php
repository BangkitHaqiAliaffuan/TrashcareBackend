<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches MarketplaceViewModel._wishlist: Set<String> (product IDs)
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('listing_id')
                ->constrained('marketplace_listings')
                ->cascadeOnDelete();
            $table->timestamps();

            // A user can only wishlist the same item once
            $table->unique(['user_id', 'listing_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
