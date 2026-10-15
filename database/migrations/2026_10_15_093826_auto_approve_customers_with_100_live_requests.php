<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('customers')
            ->whereNull('kyc_approved')
            ->whereIn('user_id', function (Builder $query) {
                $query->select('verification_requests.user_id')
                    ->from('verification_requests')
                    ->leftJoin('service_providers', 'service_providers.id', '=', 'verification_requests.service_provider_id')
                    ->where('verification_requests.status', 'completed')
                    ->where('verification_requests.amount_charged', '>', 0)
                    ->where(function (Builder $providerQuery) {
                        $providerQuery->whereNull('verification_requests.service_provider_id')
                            ->orWhere('service_providers.environment', 'live');
                    })
                    ->groupBy('verification_requests.user_id')
                    ->havingRaw('COUNT(*) >= ?', [100]);
            })
            ->update(['kyc_approved' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Approval backfills must not revoke customer approvals on rollback.
    }
};
