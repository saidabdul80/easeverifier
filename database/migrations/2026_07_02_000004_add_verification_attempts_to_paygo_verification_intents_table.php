<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('paygo_verification_intents', function (Blueprint $table) {
            $table->dropColumn('verification_attempts');
        });
    }
};
