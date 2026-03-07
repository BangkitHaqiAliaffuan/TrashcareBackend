<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each row = one TrashType chip selected on a pickup request
        // Matches PickupRequest.trashTypes: List<TrashType>
        Schema::create('pickup_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('waste_category_id')
                ->constrained()
                ->cascadeOnDelete();

            // Weight in kg — nullable at request time, filled after pickup
            $table->decimal('estimated_weight_kg', 8, 2)->nullable();
            $table->decimal('actual_weight_kg', 8, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // A request cannot have the same waste category twice
            $table->unique(['pickup_request_id', 'waste_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_items');
    }
};
