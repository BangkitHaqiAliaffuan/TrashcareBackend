<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Mayar payment request ID (UUID from Mayar API)
            $table->string('mayar_payment_id')->nullable()->after('shipping_address');
            // Full payment link returned by Mayar
            $table->string('mayar_payment_link')->nullable()->after('mayar_payment_id');
            // Payment status: unpaid | paid | closed
            $table->enum('payment_status', ['unpaid', 'paid', 'closed'])
                ->default('unpaid')
                ->after('mayar_payment_link');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['mayar_payment_id', 'mayar_payment_link', 'payment_status', 'paid_at']);
        });
    }
};
