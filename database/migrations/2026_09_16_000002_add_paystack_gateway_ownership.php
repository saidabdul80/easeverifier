<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paystack_gateway_accounts')) {
            Schema::create('paystack_gateway_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('owner_type', 20)->default('customer');
                $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->string('environment', 10);
                $table->text('public_key');
                $table->text('secret_key');
                $table->string('key_fingerprint', 24);
                $table->boolean('is_trusted')->default(false);
                $table->boolean('is_active')->default(false);
                $table->string('verification_status', 30)->default('unverified');
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('rotated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['customer_id', 'environment'], 'paystack_gateway_customer_environment_idx');
                $table->index(['customer_id', 'is_trusted', 'is_active'], 'paystack_gateway_customer_status_idx');
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_accounts', 'paystack_gateway_account_id')) {
            Schema::table('customer_paystack_split_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('paystack_gateway_account_id')->nullable()->after('customer_id');
                $table->foreign('paystack_gateway_account_id', 'cpsa_gateway_fk')->references('id')->on('paystack_gateway_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_accounts', 'beneficiary_type')) {
            Schema::table('customer_paystack_split_accounts', function (Blueprint $table) {
                $table->string('beneficiary_type', 20)->default('customer')->after('paystack_gateway_account_id');
                $table->index(['paystack_gateway_account_id', 'beneficiary_type', 'is_active'], 'paystack_split_gateway_beneficiary_idx');
            });
        }

        if (! Schema::hasColumn('paygo_verification_intents', 'paystack_gateway_account_id')) {
            Schema::table('paygo_verification_intents', function (Blueprint $table) {
                $table->unsignedBigInteger('paystack_gateway_account_id')->nullable()->after('transaction_id');
                $table->string('paystack_gateway_owner_type', 20)->default('system')->after('paystack_gateway_account_id');
                $table->string('paystack_environment', 10)->nullable()->after('paystack_gateway_owner_type');
                $table->string('paystack_key_fingerprint', 24)->nullable()->after('paystack_environment');
                $table->string('settlement_strategy', 60)->nullable()->after('paystack_key_fingerprint');
                $table->foreign('paystack_gateway_account_id', 'pvi_gateway_fk')->references('id')->on('paystack_gateway_accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('customer_paystack_split_ledgers', 'paystack_gateway_account_id')) {
            Schema::table('customer_paystack_split_ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('paystack_gateway_account_id')->nullable()->after('paygo_verification_intent_id');
                $table->string('gateway_owner_type', 20)->default('system')->after('paystack_gateway_account_id');
                $table->string('settlement_strategy', 60)->nullable()->after('gateway_owner_type');
                $table->string('beneficiary_type', 20)->default('customer')->after('settlement_strategy');
                $table->foreign('paystack_gateway_account_id', 'cpsl_gateway_fk')->references('id')->on('paystack_gateway_accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('customer_paystack_split_ledgers', function (Blueprint $table) {
            $table->dropForeign('cpsl_gateway_fk');
            $table->dropColumn(['paystack_gateway_account_id', 'gateway_owner_type', 'settlement_strategy', 'beneficiary_type']);
        });

        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->dropForeign('pvi_gateway_fk');
            $table->dropColumn(['paystack_gateway_account_id', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint', 'settlement_strategy']);
        });

        Schema::table('customer_paystack_split_accounts', function (Blueprint $table) {
            $table->dropIndex('paystack_split_gateway_beneficiary_idx');
            $table->dropForeign('cpsa_gateway_fk');
            $table->dropColumn(['paystack_gateway_account_id', 'beneficiary_type']);
        });

        Schema::dropIfExists('paystack_gateway_accounts');
    }
};
