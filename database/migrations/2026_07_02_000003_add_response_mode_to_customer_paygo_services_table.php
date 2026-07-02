<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->string('response_mode', 20)->default('redirect')->after('failure_url');
        });
    }

    public function down(): void
    {
        Schema::table('customer_paygo_services', function (Blueprint $table) {
            $table->dropColumn('response_mode');
        });
    }
};
