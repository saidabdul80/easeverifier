<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('account_type')->default('individual')->after('company_name');
            $table->string('registration_number')->nullable()->after('business_type');
            $table->string('website')->nullable()->after('state');
            $table->text('use_case')->nullable()->after('website');
            $table->string('expected_monthly_volume')->nullable()->after('use_case');
        });

        DB::table('customers')
            ->where(function ($query) {
                $query->whereNotNull('company_name')
                    ->orWhereNotNull('business_type')
                    ->orWhereNotNull('registration_number')
                    ->orWhereNotNull('website')
                    ->orWhereNotNull('use_case')
                    ->orWhereNotNull('expected_monthly_volume');
            })
            ->update(['account_type' => 'business']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'account_type',
                'registration_number',
                'website',
                'use_case',
                'expected_monthly_volume',
            ]);
        });
    }
};
