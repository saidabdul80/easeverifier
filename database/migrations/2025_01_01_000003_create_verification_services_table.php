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
        Schema::create('verification_services', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "NIN Verification", "BVN Verification"
            $table->string('slug')->unique(); // e.g., "nin", "bvn", "cac"
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // icon class for display
            $table->decimal('default_price', 10, 2)->default(0.00);
            $table->decimal('cost_price', 10, 2)->default(0.00); // what we pay the provider
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_services');
    }
};

