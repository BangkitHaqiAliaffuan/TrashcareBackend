<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);           // e.g. "Rumah", "Kantor", "Kos"
            $table->string('recipient_name');        // nama penerima
            $table->string('phone', 20);             // nomor telepon penerima
            $table->text('full_address');            // jalan, nomor, RT/RW, dll.
            $table->string('city', 100);             // kota/kabupaten
            $table->string('province', 100);         // provinsi
            $table->string('postal_code', 10);       // kode pos
            $table->boolean('is_default')->default(false); // alamat utama
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
