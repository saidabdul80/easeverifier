<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_paystack_split_ledgers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paygo_verification_intent_id');
            $table->string('payment_reference', 80);
            $table->string('split_reference', 100)->nullable();
            $table->string('subaccount_code', 80);
            $table->string('subaccount_label')->nullable();
            $table->decimal('flat_amount', 15, 2);
            $table->unsignedBigInteger('flat_amount_kobo');
            $table->decimal('transaction_amount', 15, 2);
            $table->decimal('main_account_remainder', 15, 2);
            $table->string('status', 30)->default('completed');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('customer_id', 'split_ledger_customer_fk')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('user_id', 'split_ledger_user_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('paygo_verification_intent_id', 'split_ledger_intent_fk')->references('id')->on('paygo_verification_intents')->cascadeOnDelete();
            $table->unique(['paygo_verification_intent_id', 'subaccount_code'], 'split_ledger_intent_subaccount_unique');
            $table->index(['customer_id', 'paid_at'], 'split_ledger_customer_paid_idx');
            $table->index(['user_id', 'paid_at'], 'split_ledger_user_paid_idx');
            $table->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_paystack_split_ledgers');
    }
};
