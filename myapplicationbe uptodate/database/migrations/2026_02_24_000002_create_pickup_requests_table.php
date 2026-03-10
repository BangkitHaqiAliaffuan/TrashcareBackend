<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Location — matches PickupRequest.address
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Schedule — matches PickupRequest.date + time
            $table->date('pickup_date');
            $table->time('pickup_time');

            // Matches PickupStatus enum
            $table->enum('status', ['pending', 'on_the_way', 'done', 'cancelled'])->default('pending');

            $table->text('notes')->nullable();   // matches PickupRequest.notes

            // Points granted to user upon completion
            $table->integer('points_awarded')->default(0);

            // Estimated total weight (kg) — sum across all pickup_items
            $table->decimal('estimated_weight_kg', 8, 2)->nullable();

            // Assigned courier (optional, for future use)
            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_requests');
    }
};
