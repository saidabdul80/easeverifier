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
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('bonus_balance_before', 15, 2)->default(0)->after('balance_before');
            $table->decimal('bonus_balance_after', 15, 2)->default(0)->after('balance_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['bonus_balance_before', 'bonus_balance_after']);
        });
    }
};
