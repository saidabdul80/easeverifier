<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_code', 40)->nullable()->unique()->after('result_fetch_enabled');
        });

        DB::table('customers')
            ->whereNull('referral_code')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($customer) {
                do {
                    $code = 'EVR-' . strtoupper(Str::random(8));
                } while (DB::table('customers')->where('referral_code', $code)->exists());

                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['referral_code' => $code]);
            });

        Schema::table('result_pin_orders', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')->nullable()->after('transaction_id')->constrained('users')->nullOnDelete();
            $table->string('referral_code', 40)->nullable()->after('referred_by_user_id')->index();
            $table->decimal('referral_bonus_amount', 15, 2)->default(0)->after('referral_code');
            $table->foreignId('referral_bonus_transaction_id')->nullable()->after('referral_bonus_amount')->constrained('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_pin_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_bonus_transaction_id');
            $table->dropConstrainedForeignId('referred_by_user_id');
            $table->dropColumn(['referral_code', 'referral_bonus_amount']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
