<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestWallet extends Command
{
    protected $signature = 'test:wallet
        {--user=2 : User ID to test with}
        {--recalculate : Recalculate wallet balance from completed verifications}
        {--fix : Actually apply the recalculated balance}
        {--set-balance= : Directly set wallet balance to this amount}';
    protected $description = 'Test wallet debit/credit functionality or recalculate balance';

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

        // If set-balance option is set, directly set the balance
        if ($this->option('set-balance') !== null) {
            return $this->setBalance($wallet, (float) $this->option('set-balance'));
        }

        // If recalculate option is set, just recalculate balance
        if ($this->option('recalculate')) {
            return $this->recalculateBalance($wallet);
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

    /**
     * Recalculate wallet balance based on:
     * Balance = Total Funded - (Completed Verifications × Service Price)
     */
    protected function recalculateBalance(Wallet $wallet): int
    {
        $this->info("=== WALLET BALANCE RECALCULATION ===\n");
        $this->line("User: " . $wallet->user->name . " (ID: {$wallet->user_id})");

        $user = $wallet->user;
        $currentBalance = (float) $wallet->balance;
        $currentBonusBalance = (float) $wallet->bonus_balance;

        // Get total funded amount (credits with category 'funding')
        $totalFunded = (float) $wallet->transactions()
            ->where('type', 'credit')
            ->where('category', 'funding')
            ->sum('amount');

        // Get bonus credits
        $bonusCredits = (float) $wallet->transactions()
            ->where('type', 'credit')
            ->where('category', 'bonus')
            ->sum('amount');

        // Count completed verifications and calculate total spent
        $completedVerifications = $user->verificationRequests()
            ->where('status', 'completed')
            ->count();

        // Get NIN service price (or use amount_charged from verifications)
        $ninService = \App\Models\VerificationService::where('slug', 'nin')->first();
        $pricePerVerification = $ninService ? $user->getPriceForService($ninService) : 150;

        // Calculate total spent on completed verifications
        $totalSpent = $completedVerifications * $pricePerVerification;

        // Expected balance = Funded + Bonus - Spent on completed verifications
        $expectedBalance = $totalFunded + $bonusCredits - $totalSpent;

        $this->line("\nCurrent wallet balance: ₦" . number_format($currentBalance, 2));
        $this->line("Current bonus balance: ₦" . number_format($currentBonusBalance, 2));

        $this->line("\n📊 Calculation based on completed verifications:");
        $this->line("  Total funded: ₦" . number_format($totalFunded, 2));
        $this->line("  Bonus credits: ₦" . number_format($bonusCredits, 2));
        $this->line("  Completed verifications: " . $completedVerifications);
        $this->line("  Price per verification: ₦" . number_format($pricePerVerification, 2));
        $this->line("  Total spent ({$completedVerifications} × ₦{$pricePerVerification}): ₦" . number_format($totalSpent, 2));
        $this->line("  ─────────────────────────────");
        $this->line("  Expected balance: ₦" . number_format($expectedBalance, 2));

        // Check for mismatch
        $balanceDiff = $currentBalance - $expectedBalance;

        if (abs($balanceDiff) < 0.01) {
            $this->info("\n✅ Wallet balance is correct. No fix needed.");
            return 0;
        }

        $this->warn("\n⚠️  MISMATCH DETECTED!");
        $this->line("Difference: ₦" . number_format(abs($balanceDiff), 2) .
            ($balanceDiff > 0 ? " (wallet has MORE than expected)" : " (wallet has LESS than expected)"));

        if ($this->option('fix')) {
            $this->warn("\n🔧 FIXING wallet balance...");

            DB::transaction(function () use ($wallet, $expectedBalance) {
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
                $lockedWallet->balance = max(0, $expectedBalance);
                $lockedWallet->save();
            });

            $wallet->refresh();
            $this->info("✅ Wallet balance updated!");
            $this->line("New balance: ₦" . number_format($wallet->balance, 2));
        } else {
            $this->line("\nTo fix, run: php artisan test:wallet --user={$wallet->user_id} --recalculate --fix");
        }

        return 0;
    }

    /**
     * Directly set wallet balance (uses DB lock to prevent race conditions).
     */
    protected function setBalance(Wallet $wallet, float $newBalance): int
    {
        $this->info("=== SET WALLET BALANCE ===\n");
        $this->line("User: " . $wallet->user->name . " (ID: {$wallet->user_id})");
        $this->line("Current balance: ₦" . number_format($wallet->balance, 2));
        $this->line("New balance: ₦" . number_format($newBalance, 2));

        if (!$this->confirm("Are you sure you want to set the balance to ₦" . number_format($newBalance, 2) . "?")) {
            $this->info("Cancelled.");
            return 0;
        }

        DB::transaction(function () use ($wallet, $newBalance) {
            // Lock the wallet row to prevent concurrent updates
            $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            $lockedWallet->balance = $newBalance;
            $lockedWallet->save();
        });

        $wallet->refresh();
        $this->info("\n✅ Wallet balance updated!");
        $this->line("New balance: ₦" . number_format($wallet->balance, 2));

        return 0;
    }
}

