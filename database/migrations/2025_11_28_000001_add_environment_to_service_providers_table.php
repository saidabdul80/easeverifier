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
        Schema::table('service_providers', function (Blueprint $table) {
            $table->enum('environment', ['test', 'live'])->default('live')->after('is_active');
            $table->index(['verification_service_id', 'is_active', 'environment', 'priority'], 'service_providers_env_priority_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropIndex('service_providers_env_priority_index');
            $table->dropColumn('environment');
        });
    }
};

