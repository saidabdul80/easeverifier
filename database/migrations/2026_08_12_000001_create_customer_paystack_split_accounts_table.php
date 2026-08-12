<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_paystack_split_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('subaccount_code', 80)->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code', 20);
            $table->string('account_number');
            $table->string('account_number_last4', 4)->nullable();
            $table->decimal('flat_amount', 15, 2);
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'subaccount_code'], 'customer_split_subaccount_unique');
            $table->index(['customer_id', 'is_active'], 'customer_split_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_paystack_split_accounts');
    }
};
