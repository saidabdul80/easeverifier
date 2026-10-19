<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paygo_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paygo_verification_intent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('reference')->unique();
            $table->string('status', 40);
            $table->foreignId('paystack_gateway_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('paystack_gateway_owner_type', 20)->default('system');
            $table->string('paystack_environment', 10)->nullable();
            $table->string('paystack_key_fingerprint', 24)->nullable();
            $table->string('settlement_strategy', 60)->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('system_price_snapshot', 10, 2);
            $table->text('authorization_url')->nullable();
            $table->string('access_code')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('duplicate_payment_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['paygo_verification_intent_id', 'attempt_number'], 'paygo_payment_attempt_number_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygo_payment_attempts');
    }
};
