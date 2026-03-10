<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            // 1. Drop the old FK that pointed to users
            $table->dropForeign(['courier_id']);

            // 2. Re-add as FK pointing to the new couriers table
            $table->foreign('courier_id')
                  ->references('id')
                  ->on('couriers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pickup_requests', function (Blueprint $table) {
            // Revert: drop FK to couriers, restore FK to users
            $table->dropForeign(['courier_id']);

            $table->foreign('courier_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }
};
