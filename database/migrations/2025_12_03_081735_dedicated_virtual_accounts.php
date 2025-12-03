<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dedicated_virtual_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('bank_id');
            $table->string('bank_slug');
            $table->string('customer_id'); // Paystack customer ID
            $table->string('customer_code')->unique(); // Paystack customer code
            $table->string('account_reference')->nullable(); // Paystack account ID
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('customer_code');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dedicated_virtual_accounts');
    }
};