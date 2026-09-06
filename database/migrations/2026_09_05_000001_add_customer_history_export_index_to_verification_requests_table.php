<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->index(
                ['user_id', 'verification_service_id', 'created_at', 'id'],
                'vr_customer_service_created_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->dropIndex('vr_customer_service_created_id_idx');
        });
    }
};
