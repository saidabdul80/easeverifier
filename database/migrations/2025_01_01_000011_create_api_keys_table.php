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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default');
            $table->string('key', 64)->unique(); // ev_live_xxx or ev_test_xxx
            $table->string('secret_hash', 64); // hashed secret for validation
            $table->string('plain_secret', 64)->nullable(); // Only shown once on creation
            $table->enum('environment', ['live', 'test'])->default('live');
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable(); // Optional: specific permissions for this key
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->integer('rate_limit')->default(100); // Requests per minute
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['key', 'is_active']);
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};

