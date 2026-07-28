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
        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->string('callback_mode', 20)
                ->default('redirect')
                ->after('response_mode');
            $table->text('webhook_secret')
                ->nullable()
                ->after('callback_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->dropColumn([
                'callback_mode',
                'webhook_secret',
            ]);
        });
    }
};
