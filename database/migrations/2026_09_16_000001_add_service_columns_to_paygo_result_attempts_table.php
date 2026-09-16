<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paygo_result_attempts', function (Blueprint $table) {
            if (! Schema::hasColumn('paygo_result_attempts', 'customer_paygo_service_id')) {
                $table->foreignId('customer_paygo_service_id')
                    ->nullable()
                    ->after('paygo_verification_intent_id')
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('paygo_result_attempts', 'verification_service_id')) {
                $table->foreignId('verification_service_id')
                    ->nullable()
                    ->after('customer_paygo_service_id')
                    ->constrained()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paygo_result_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('paygo_result_attempts', 'verification_service_id')) {
                $table->dropConstrainedForeignId('verification_service_id');
            }

            if (Schema::hasColumn('paygo_result_attempts', 'customer_paygo_service_id')) {
                $table->dropConstrainedForeignId('customer_paygo_service_id');
            }
        });
    }
};
