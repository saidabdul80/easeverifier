<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_paygo_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('public_slug')->unique();
            $table->string('verify_secret_hash', 64);
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->string('success_url')->nullable();
            $table->string('failure_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['verification_service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_paygo_services');
    }
};
