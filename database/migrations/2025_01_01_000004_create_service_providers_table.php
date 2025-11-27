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
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_service_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Provider name e.g., "NIMC API", "VerifyMe"
            $table->string('base_url');
            $table->string('endpoint');
            $table->enum('http_method', ['GET', 'POST', 'PUT', 'PATCH'])->default('POST');
            $table->enum('auth_type', ['none', 'bearer', 'api_key_header', 'api_key_body', 'basic', 'custom'])->default('bearer');
            $table->json('auth_config')->nullable(); // Store auth details based on type
            $table->json('request_headers')->nullable(); // Additional headers
            $table->json('request_body_template')->nullable(); // Template for request body
            $table->json('response_mapping')->nullable(); // How to map provider response to our format
            $table->integer('timeout')->default(30); // Request timeout in seconds
            $table->integer('priority')->default(1); // For failover - lower number = higher priority
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['verification_service_id', 'is_active', 'priority'], 'service_providers_priority_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};

