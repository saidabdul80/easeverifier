<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletTransferService
{
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        string $description,
        array $metadata = [],
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        if ($fromWallet->id === $toWallet->id) {
            throw new \InvalidArgumentException('Source and destination wallets must be different.');
        }

        return DB::transaction(function () use ($fromWallet, $toWallet, $amount, $description, $metadata) {
            [$firstId, $secondId] = collect([$fromWallet->id, $toWallet->id])->sort()->values()->all();

            $lockedWallets = Wallet::query()
                ->whereIn('id', [$firstId, $secondId])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedFrom = $lockedWallets[$fromWallet->id];
            $lockedTo = $lockedWallets[$toWallet->id];

            if (! $lockedFrom->hasSufficientFunds($amount)) {
                throw new \RuntimeException('Insufficient funds');
            }

            $fromBalanceBefore = $lockedFrom->balance;
            $fromBonusBefore = $lockedFrom->bonus_balance;
            $remaining = $amount;
            $bonusDeduction = 0;

            if ($lockedFrom->bonus_balance > 0) {
                $bonusDeduction = min($lockedFrom->bonus_balance, $remaining);
                $lockedFrom->bonus_balance -= $bonusDeduction;
                $remaining -= $bonusDeduction;
            }

            $lockedFrom->balance -= $remaining;
            $lockedFrom->save();

            $toBalanceBefore = $lockedTo->balance;
            $toBonusBefore = $lockedTo->bonus_balance;
            $lockedTo->balance += $amount;
            $lockedTo->save();

            $debitTransaction = $lockedFrom->transactions()->create([
                'user_id' => $lockedFrom->user_id,
                'reference' => Transaction::generateReference(),
                'type' => 'debit',
                'category' => 'adjustment',
                'amount' => $amount,
                'balance_before' => $fromBalanceBefore,
                'bonus_balance_before' => $fromBonusBefore,
                'balance_after' => $lockedFrom->balance,
                'bonus_balance_after' => $lockedFrom->bonus_balance,
                'description' => $description,
                'metadata' => array_merge($metadata, [
                    'transfer_direction' => 'out',
                    'bonus_deducted' => $bonusDeduction,
                    'main_deducted' => $remaining,
                    'counterparty_wallet_id' => $lockedTo->id,
                ]),
                'status' => 'completed',
            ]);

            $creditTransaction = $lockedTo->transactions()->create([
                'user_id' => $lockedTo->user_id,
                'reference' => Transaction::generateReference(),
                'type' => 'credit',
                'category' => 'adjustment',
                'amount' => $amount,
                'balance_before' => $toBalanceBefore,
                'bonus_balance_before' => $toBonusBefore,
                'balance_after' => $lockedTo->balance,
                'bonus_balance_after' => $lockedTo->bonus_balance,
                'description' => $description,
                'metadata' => array_merge($metadata, [
                    'transfer_direction' => 'in',
                    'counterparty_wallet_id' => $lockedFrom->id,
                ]),
                'status' => 'completed',
            ]);

            return [
                'debit' => $debitTransaction,
                'credit' => $creditTransaction,
            ];
        });
    }
}
