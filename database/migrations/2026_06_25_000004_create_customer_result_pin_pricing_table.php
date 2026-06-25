<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_result_pin_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('result_pin_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'result_pin_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_result_pin_pricing');
    }
};
