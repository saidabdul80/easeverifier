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
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('paygo_result_reference_fetch_limit')
                ->default(3)
                ->after('result_fetch_enabled');
        });

        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->string('nin_hash', 64)->nullable()->change();
            $table->string('flow_type', 30)->default('identity')->after('verification_service_id');
            $table->string('lookup_hash', 64)->nullable()->after('nin_hash');
            $table->string('lookup_label')->nullable()->after('lookup_hash');
            $table->longText('payload')->nullable()->after('lookup_label');
            $table->unsignedSmallInteger('max_fetches_snapshot')->default(3)->after('verification_attempts');
            $table->unsignedSmallInteger('reference_fetches')->default(0)->after('max_fetches_snapshot');

            $table->index(['flow_type', 'status'], 'pg_intents_flow_status_idx');
            $table->index(['lookup_hash', 'status'], 'pg_intents_lookup_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->dropIndex('pg_intents_lookup_status_idx');
            $table->dropIndex('pg_intents_flow_status_idx');
            $table->dropColumn([
                'flow_type',
                'lookup_hash',
                'lookup_label',
                'payload',
                'max_fetches_snapshot',
                'reference_fetches',
            ]);
            $table->string('nin_hash', 64)->nullable(false)->change();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('paygo_result_reference_fetch_limit');
        });
    }
};
