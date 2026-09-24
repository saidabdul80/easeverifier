<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('paygo_verification_intents')
            ->where('flow_type', 'result_reference')
            ->whereNotNull('paid_at')
            ->whereIn('status', ['paid', 'verifying', 'expired'])
            ->orderBy('id')
            ->chunkById(200, function ($intents): void {
                foreach ($intents as $intent) {
                    $extendedExpiry = Carbon::parse($intent->paid_at)->addDays(10);
                    $currentExpiry = $intent->expires_at ? Carbon::parse($intent->expires_at) : null;

                    if ($currentExpiry && $currentExpiry->greaterThanOrEqualTo($extendedExpiry)) {
                        continue;
                    }

                    $updates = ['expires_at' => $extendedExpiry];

                    if ($intent->status === 'expired' && $extendedExpiry->isFuture()) {
                        $updates['status'] = 'paid';
                    }

                    DB::table('paygo_verification_intents')
                        ->where('id', $intent->id)
                        ->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Existing payment validity should not be shortened during rollback.
    }
};
