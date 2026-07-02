<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paygo_verification_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_paygo_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verification_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('nin_hash', 64);
            $table->decimal('amount', 10, 2);
            $table->decimal('system_price_snapshot', 10, 2);
            $table->enum('status', ['pending', 'paid', 'verifying', 'used', 'failed', 'expired'])->default('pending');
            $table->string('buyer_phone', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_paygo_service_id', 'status'], 'pg_intents_service_status_idx');
            $table->index(['user_id', 'status', 'created_at'], 'pg_intents_user_status_created_idx');
            $table->index(['nin_hash', 'status'], 'pg_intents_nin_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygo_verification_intents');
    }
};
