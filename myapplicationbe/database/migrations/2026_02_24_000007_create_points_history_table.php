<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks points_balance changes on users — matches UserProfile.co2_saved / stats
        Schema::create('points_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Positive = earned, negative = spent
            $table->integer('points');

            $table->enum('type', ['earned', 'spent']);

            // Where points came from / went to
            $table->enum('source', [
                'pickup_completed',  // earned after pickup done
                'item_sold',         // earned when marketplace sale completes
                'redeem_reward',     // spent on reward
                'bonus',             // admin bonus / promo
                'adjustment',        // manual correction
            ]);

            // Polymorphic reference — e.g. pickup_request id or order id
            $table->string('referenceable_type')->nullable();
            $table->unsignedBigInteger('referenceable_id')->nullable();

            $table->string('description')->nullable(); // human-readable note
            $table->integer('balance_after');          // snapshot of balance after this transaction

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['referenceable_type', 'referenceable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_history');
    }
};
