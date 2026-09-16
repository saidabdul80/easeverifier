<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('paygo_result_reference_system_price', 10, 2)
                ->nullable()
                ->after('paygo_result_reference_fetch_limit');
        });

        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->decimal('reference_price', 10, 2)
                ->nullable()
                ->after('price');
        });

        Schema::create('paygo_result_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paygo_verification_intent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_paygo_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verification_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verification_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lookup_hash', 64);
            $table->string('lookup_label')->nullable();
            $table->longText('payload')->nullable();
            $table->string('status', 30)->default('pending_payment');
            $table->unsignedSmallInteger('attempt_number')->default(0);
            $table->boolean('success_counted')->default(false);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['paygo_verification_intent_id', 'status'], 'pg_result_attempts_intent_status_idx');
            $table->index(['lookup_hash', 'status'], 'pg_result_attempts_lookup_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paygo_result_attempts');

        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->dropColumn('reference_price');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('paygo_result_reference_system_price');
        });
    }
};
