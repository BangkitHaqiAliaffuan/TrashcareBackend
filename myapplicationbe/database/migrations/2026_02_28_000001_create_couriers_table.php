<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();

            // Basic identity
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');

            // Profile
            $table->string('avatar_path')->nullable();
            $table->string('vehicle_type', 50)->nullable();   // motor, mobil, etc.
            $table->string('vehicle_plate', 20)->nullable();  // nomor plat

            // Operational status
            $table->enum('status', ['active', 'inactive', 'on_duty'])->default('active');
            $table->boolean('is_available')->default(true);

            // Rating & stats
            $table->decimal('rating', 3, 2)->default(0.00);  // 0.00 – 5.00
            $table->integer('total_deliveries')->default(0);

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
