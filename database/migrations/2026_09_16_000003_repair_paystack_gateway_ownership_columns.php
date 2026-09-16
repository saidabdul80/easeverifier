<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_paystack_split_accounts', 'beneficiary_type')) {
            Schema::table('customer_paystack_split_accounts', function (Blueprint $table) {
                $table->string('beneficiary_type', 20)->default('customer')->after('paystack_gateway_account_id');
            });
        }

        if (! Schema::hasColumn('paygo_verification_intents', 'paystack_gateway_owner_type')) {
            Schema::table('paygo_verification_intents', function (Blueprint $table) {
                $table->string('paystack_gateway_owner_type', 20)->default('system')->after('paystack_gateway_account_id');
            });
        }

        if (! Schema::hasColumn('paygo_verification_intents', 'paystack_environment')) {
            Schema::table('paygo_verification_intents', function (Blueprint $table) {
                $table->string('paystack_environment', 10)->nullable()->after('paystack_gateway_owner_type');
            });
        }

        if (! Schema::hasColumn('paygo_verification_intents', 'paystack_key_fingerprint')) {
            Schema::table('paygo_verification_intents', function (Blueprint $table) {
                $table->string('paystack_key_fingerprint', 24)->nullable()->after('paystack_environment');
            });
        }

        if (! Schema::hasColumn('paygo_verification_intents', 'settlement_strategy')) {
            Schema::table('paygo_verification_intents', function (Blueprint $table) {
                $table->string('settlement_strategy', 60)->nullable()->after('paystack_key_fingerprint');
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_ledgers', 'gateway_owner_type')) {
            Schema::table('customer_paystack_split_ledgers', function (Blueprint $table) {
                $table->string('gateway_owner_type', 20)->default('system')->after('paystack_gateway_account_id');
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_ledgers', 'settlement_strategy')) {
            Schema::table('customer_paystack_split_ledgers', function (Blueprint $table) {
                $table->string('settlement_strategy', 60)->nullable()->after('gateway_owner_type');
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_ledgers', 'beneficiary_type')) {
            Schema::table('customer_paystack_split_ledgers', function (Blueprint $table) {
                $table->string('beneficiary_type', 20)->default('customer')->after('settlement_strategy');
            });
        }

        if (! Schema::hasIndex('customer_paystack_split_accounts', 'paystack_split_gateway_beneficiary_idx')) {
            Schema::table('customer_paystack_split_accounts', function (Blueprint $table) {
                $table->index(['paystack_gateway_account_id', 'beneficiary_type', 'is_active'], 'paystack_split_gateway_beneficiary_idx');
            });
        }
    }

    public function down(): void
    {
        // This migration repairs a potentially partial production migration.
        // Its columns remain owned by the original gateway ownership migration.
    }
};
