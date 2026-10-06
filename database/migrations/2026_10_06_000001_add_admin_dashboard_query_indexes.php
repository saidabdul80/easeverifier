<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'vr_admin_status_created_idx');
            $table->index(['created_at', 'id'], 'vr_admin_created_id_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'tx_admin_status_created_idx');
        });

        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'pg_intents_admin_status_created_idx');
            $table->index(['created_at', 'id'], 'pg_intents_admin_created_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->dropIndex('pg_intents_admin_created_id_idx');
            $table->dropIndex('pg_intents_admin_status_created_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('tx_admin_status_created_idx');
        });

        Schema::table('verification_requests', function (Blueprint $table) {
            $table->dropIndex('vr_admin_created_id_idx');
            $table->dropIndex('vr_admin_status_created_idx');
        });
    }
};
