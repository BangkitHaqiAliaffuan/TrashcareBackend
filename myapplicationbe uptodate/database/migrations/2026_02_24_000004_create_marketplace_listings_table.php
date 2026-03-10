<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Matches Product data class + ProductCategory + ProductCondition enums
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->string('name');                       // Product.name
            $table->text('description');                  // Product.description
            $table->unsignedBigInteger('price');          // Product.price (IDR, no decimal)

            // Matches ProductCategory enum (minus ALL which is a filter-only value)
            $table->enum('category', [
                'furniture',
                'electronics',
                'clothing',
                'books',
                'others',
            ])->default('others');

            // Matches ProductCondition enum
            $table->enum('condition', [
                'like_new',   // ProductCondition.LIKE_NEW
                'good',       // ProductCondition.GOOD
                'fair',       // ProductCondition.FAIR
            ])->default('good');

            $table->string('image_path')->nullable();     // Product.imageUrl → stored path
            $table->string('seller_name');                // Product.sellerName (denormalized for display speed)
            $table->decimal('seller_rating', 3, 1)->default(0.0); // Product.sellerRating

            $table->boolean('is_sold')->default(false);
            $table->boolean('is_active')->default(true);  // soft-hide without deleting
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_sold', 'is_active']); // optimise category filter queries
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
