<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paygo_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paygo_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('category', ['earning', 'withdrawal', 'adjustment']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('completed');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['paygo_wallet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygo_wallet_transactions');
    }
};
