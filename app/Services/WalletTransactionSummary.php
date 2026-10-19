<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class WalletTransactionSummary
{
    /**
     * @param  Builder<Transaction>  $transactions
     * @return array{total_credits: float, total_debits: float, total_refunds: float}
     */
    public function summarize(Builder $transactions): array
    {
        $totals = (clone $transactions)
            ->where('status', 'completed')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' AND category != 'refund' THEN amount ELSE 0 END), 0) as total_credits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'debit' THEN amount WHEN type = 'credit' AND category = 'refund' THEN -amount ELSE 0 END), 0) as total_debits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'credit' AND category = 'refund' THEN amount ELSE 0 END), 0) as total_refunds")
            ->first();

        return [
            'total_credits' => round((float) $totals->total_credits, 2),
            'total_debits' => round((float) $totals->total_debits, 2),
            'total_refunds' => round((float) $totals->total_refunds, 2),
        ];
    }
}
