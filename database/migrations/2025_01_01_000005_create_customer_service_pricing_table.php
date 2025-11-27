<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Custom pricing per customer per service
        Schema::create('customer_service_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('verification_service_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2); // Custom price for this customer
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'verification_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_service_pricing');
    }
};

