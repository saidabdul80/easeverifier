<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestWallet extends Command
{
    protected $signature = 'test:wallet {--user=2 : User ID to test with}';
    protected $description = 'Test wallet debit/credit functionality';

    public function handle()
    {
        $userId = $this->option('user');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return 1;
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            $this->error("User {$userId} has no wallet");
            return 1;
        }

        $this->info("=== WALLET TEST FOR USER: {$user->name} ===\n");

        // Test 1: Check initial balance
        $this->info("TEST 1: Initial Balance Check");
        $wallet->refresh();
        $initialBalance = (float) $wallet->balance;
        $initialBonus = (float) $wallet->bonus_balance;
        $initialTotal = $initialBalance + $initialBonus;
        $this->line("  Main Balance: ₦{$initialBalance}");
        $this->line("  Bonus Balance: ₦{$initialBonus}");
        $this->line("  Total: ₦{$initialTotal}");

        // Test 2: Debit test (checks total balance deduction)
        $this->info("\nTEST 2: Debit ₦100 (from total balance)");
        $debitAmount = 100;

        $wallet->refresh();
        if (!$wallet->hasSufficientFunds($debitAmount)) {
            $this->error("  Insufficient funds to test debit");
            return 1;
        }

        $totalBeforeDebit = (float) $wallet->balance + (float) $wallet->bonus_balance;
        $transaction = $wallet->debit($debitAmount, 'verification', 'Test debit transaction');
        $wallet->refresh();
        $totalAfterDebit = (float) $wallet->balance + (float) $wallet->bonus_balance;

        $this->line("  Total Before: ₦{$totalBeforeDebit}");
        $this->line("  Total After: ₦{$totalAfterDebit}");
        $this->line("  Transaction Amount: ₦{$transaction->amount}");
        $this->line("  Bonus Used: ₦" . ($transaction->metadata['bonus_deducted'] ?? 0));
        $this->line("  Main Used: ₦" . ($transaction->metadata['main_deducted'] ?? 0));

        $expectedAfterDebit = $totalBeforeDebit - $debitAmount;
        if (abs($totalAfterDebit - $expectedAfterDebit) < 0.01) {
            $this->info("  ✅ PASS: Total balance correctly debited");
        } else {
            $this->error("  ❌ FAIL: Expected ₦{$expectedAfterDebit}, got ₦{$totalAfterDebit}");
        }

        // Test 3: Credit test (refund to main balance)
        $this->info("\nTEST 3: Credit ₦100 (refund)");

        $wallet->refresh();
        $balanceBeforeCredit = (float) $wallet->balance;
        $transaction = $wallet->credit($debitAmount, 'refund', 'Test credit transaction');
        $wallet->refresh();
        $balanceAfterCredit = (float) $wallet->balance;

        $this->line("  Main Before: ₦{$balanceBeforeCredit}");
        $this->line("  Main After: ₦{$balanceAfterCredit}");
        $this->line("  Transaction Amount: ₦{$transaction->amount}");

        $expectedAfterCredit = $balanceBeforeCredit + $debitAmount;
        if (abs($balanceAfterCredit - $expectedAfterCredit) < 0.01) {
            $this->info("  ✅ PASS: Balance correctly credited");
        } else {
            $this->error("  ❌ FAIL: Expected ₦{$expectedAfterCredit}, got ₦{$balanceAfterCredit}");
        }

        // Test 4: Verify total balance integrity
        $this->info("\nTEST 4: Total Balance Integrity Check");
        $wallet->refresh();
        $finalTotal = (float) $wallet->balance + (float) $wallet->bonus_balance;

        if (abs($finalTotal - $initialTotal) < 0.01) {
            $this->info("  ✅ PASS: Total restored to initial value ₦{$finalTotal}");
        } else {
            $this->error("  ❌ FAIL: Total mismatch. Initial: ₦{$initialTotal}, Final: ₦{$finalTotal}");
        }

        // Test 5: Concurrent simulation (checks DB row locking)
        $this->info("\nTEST 5: DB Refresh & Lock Test (simulates concurrent access)");

        $wallet->refresh();
        $startBalance = (float) $wallet->balance;

        // Load wallet twice (simulating two concurrent requests)
        $wallet1 = Wallet::find($wallet->id);
        $wallet2 = Wallet::find($wallet->id);

        $this->line("  Starting balance: ₦{$startBalance}");

        // Debit from wallet1
        $wallet1->debit(50, 'verification', 'Concurrent test 1');
        $dbBalanceAfter1 = (float) Wallet::find($wallet->id)->balance;
        $this->line("  After wallet1 debit ₦50: DB shows ₦{$dbBalanceAfter1}");

        // Debit from wallet2 (should use fresh DB data due to lockForUpdate)
        $wallet2->debit(50, 'verification', 'Concurrent test 2');
        $dbBalanceAfter2 = (float) Wallet::find($wallet->id)->balance;
        $this->line("  After wallet2 debit ₦50: DB shows ₦{$dbBalanceAfter2}");

        $expectedFinal = $startBalance - 100;
        if (abs($dbBalanceAfter2 - $expectedFinal) < 0.01) {
            $this->info("  ✅ PASS: Concurrent debits handled correctly");
        } else {
            $this->error("  ❌ FAIL: Expected ₦{$expectedFinal}, got ₦{$dbBalanceAfter2}");
        }

        // Cleanup: restore balance
        Wallet::find($wallet->id)->credit(100, 'refund', 'Test cleanup');
        $this->info("\n  Cleanup: Restored ₦100 to wallet");

        // Summary
        $wallet->refresh();
        $this->info("\n=== TEST COMPLETE ===");
        $this->line("Final balance: ₦{$wallet->balance}");
        $this->line("Final bonus: ₦{$wallet->bonus_balance}");

        return 0;
    }
}

