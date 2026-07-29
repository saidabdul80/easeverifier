<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE verification_requests MODIFY source ENUM('web', 'api', 'paygo') NOT NULL DEFAULT 'web'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE verification_requests SET source = 'api' WHERE source = 'paygo'");
        DB::statement("ALTER TABLE verification_requests MODIFY source ENUM('web', 'api') NOT NULL DEFAULT 'web'");
    }
};
