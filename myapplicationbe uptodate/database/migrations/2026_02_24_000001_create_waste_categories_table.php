<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_categories', function (Blueprint $table) {
            $table->id();
            // Matches TrashType enum: organic, plastic, electronic, glass
            $table->enum('type', ['organic', 'plastic', 'electronic', 'glass'])->unique();
            $table->string('label');           // "Organic", "Plastic", etc.
            $table->string('emoji', 10);       // "🌿", "♻️", etc.
            $table->string('description')->nullable();
            // Points awarded per kg of this waste type
            $table->integer('points_per_kg')->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_categories');
    }
};
