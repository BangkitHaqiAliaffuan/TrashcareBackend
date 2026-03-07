<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the status enum to add 'searching' as the first value (new default)
        DB::statement("ALTER TABLE pickup_requests
            MODIFY COLUMN status
            ENUM('searching','pending','on_the_way','done','cancelled')
            NOT NULL DEFAULT 'searching'");
    }

    public function down(): void
    {
        // Revert: change existing 'searching' rows back to 'pending' before removing enum value
        DB::statement("UPDATE pickup_requests SET status = 'pending' WHERE status = 'searching'");

        DB::statement("ALTER TABLE pickup_requests
            MODIFY COLUMN status
            ENUM('pending','on_the_way','done','cancelled')
            NOT NULL DEFAULT 'pending'");
    }
};
