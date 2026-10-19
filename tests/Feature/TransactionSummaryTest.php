<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('Transaction summary tests require sqlite :memory: before Laravel boots.');
}

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-10-19 12:00:00'));
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

function transactionSummaryUser(string $role = 'customer'): User
{
    Role::findOrCreate($role);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole($role);
    $user->wallet()->create([
        'balance' => 0,
        'bonus_balance' => 0,
        'currency' => 'NGN',
        'is_active' => true,
    ]);

    return $user;
}

function transactionSummaryEntry(User $user, string $type, string $category, float $amount, array $attributes = []): Transaction
{
    return Transaction::unguarded(fn () => $user->transactions()->create(array_merge([
        'wallet_id' => $user->wallet->id,
        'reference' => Transaction::generateReference(),
        'type' => $type,
        'category' => $category,
        'amount' => $amount,
        'balance_before' => 0,
        'balance_after' => 0,
        'description' => 'Transaction summary test',
        'status' => 'completed',
    ], $attributes)));
}

function transactionSummaryRepeatedAttempts(User $user): Wallet
{
    $wallet = $user->wallet;
    $wallet->credit(5500, 'funding', 'Initial funding');

    foreach (range(1, 5) as $attempt) {
        $debit = $wallet->debit(5340, 'verification', 'Verification attempt');

        if ($attempt < 5) {
            $wallet->credit(5340, 'refund', 'Failed verification refund', [
                'verification_request_id' => $attempt,
                'refunded_transaction_id' => $debit->id,
            ]);
        }
    }

    return $wallet;
}

it('nets repeated charges against refunds without changing the transaction history', function (array $filters, int $visibleTransactions) {
    $customer = transactionSummaryUser();
    $wallet = transactionSummaryRepeatedAttempts($customer);
    $otherCustomer = transactionSummaryUser();
    transactionSummaryEntry($otherCustomer, 'debit', 'verification', 9999);
    transactionSummaryEntry($otherCustomer, 'credit', 'refund', 999);

    $this->actingAs($customer)
        ->get(route('customer.transactions.index', $filters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Transactions/Index')
            ->where('stats.total_credits', 5500)
            ->where('stats.total_debits', 5340)
            ->where('stats.total_refunds', 21360)
            ->where('stats.this_month_credits', 5500)
            ->where('stats.this_month_debits', 5340)
            ->where('stats.this_month_refunds', 21360)
            ->where('transactions.total', $visibleTransactions));

    expect((float) $wallet->fresh()->balance)->toBe(160.0)
        ->and($wallet->transactions()->where('type', 'debit')->where('status', 'completed')->count())->toBe(5)
        ->and($wallet->transactions()->where('category', 'refund')->count())->toBe(4);
})->with([
    'all transactions' => [[], 10],
    'debits only' => [['type' => 'debit'], 5],
    'refunds only' => [['category' => 'refund'], 4],
]);

it('shows wallet spending after refunds alongside the refunded total', function () {
    $customer = transactionSummaryUser();
    transactionSummaryRepeatedAttempts($customer);
    $otherCustomer = transactionSummaryUser();
    transactionSummaryEntry($otherCustomer, 'credit', 'refund', 1000);

    $this->actingAs($customer)
        ->get(route('customer.wallet.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Wallet/Index')
            ->where('stats.total_funded', 5500)
            ->where('stats.total_spent', 5340)
            ->where('stats.total_refunds', 21360)
            ->where('stats.balance', '160.00'));
});

it('excludes uncompleted entries and handles partial refunds with decimal amounts', function () {
    $customer = transactionSummaryUser();
    transactionSummaryEntry($customer, 'credit', 'funding', 200.25);
    transactionSummaryEntry($customer, 'debit', 'verification', 100.25);
    transactionSummaryEntry($customer, 'credit', 'refund', 40.10);

    foreach (['pending', 'failed', 'reversed'] as $status) {
        transactionSummaryEntry($customer, 'credit', 'funding', 500, ['status' => $status]);
        transactionSummaryEntry($customer, 'debit', 'verification', 500, ['status' => $status]);
        transactionSummaryEntry($customer, 'credit', 'refund', 500, ['status' => $status]);
    }

    $this->actingAs($customer)
        ->get(route('customer.transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_credits', 200.25)
            ->where('stats.total_debits', 60.15)
            ->where('stats.total_refunds', 40.1)
            ->where('stats.this_month_debits', 60.15)
            ->where('stats.this_month_refunds', 40.1));
});

it('counts refunds in the month they were issued including refunds of earlier charges', function () {
    $customer = transactionSummaryUser();
    transactionSummaryEntry($customer, 'debit', 'verification', 100, ['created_at' => now()->subMonth()]);
    transactionSummaryEntry($customer, 'credit', 'refund', 100);
    transactionSummaryEntry($customer, 'debit', 'verification', 50, ['created_at' => now()->subYear()]);
    transactionSummaryEntry($customer, 'credit', 'refund', 50, ['created_at' => now()->subYear()]);

    $this->actingAs($customer)
        ->get(route('customer.transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_debits', 0)
            ->where('stats.total_refunds', 150)
            ->where('stats.this_month_debits', -100)
            ->where('stats.this_month_refunds', 100));
});

it('uses the same completed net debit totals across the admin transaction summaries', function () {
    $admin = transactionSummaryUser('admin');
    $customer = transactionSummaryUser();
    transactionSummaryRepeatedAttempts($customer);
    $otherCustomer = transactionSummaryUser();
    transactionSummaryEntry($otherCustomer, 'debit', 'verification', 100, ['created_at' => now()->subDay()]);
    transactionSummaryEntry($otherCustomer, 'credit', 'refund', 100);

    foreach (['pending', 'failed', 'reversed'] as $status) {
        transactionSummaryEntry($customer, 'credit', 'funding', 500, ['status' => $status]);
        transactionSummaryEntry($customer, 'debit', 'verification', 500, ['status' => $status]);
        transactionSummaryEntry($customer, 'credit', 'refund', 500, ['status' => $status]);
    }

    $this->actingAs($admin)
        ->get(route('admin.transactions.index', ['type' => 'debit']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Transactions/Index')
            ->where('stats.total_credits', 5500)
            ->where('stats.total_debits', 5340)
            ->where('stats.total_refunds', 21460)
            ->where('stats.today_credits', 5500)
            ->where('stats.today_debits', 5240)
            ->where('stats.today_refunds', 21460));
});

it('returns zero totals for a customer without transactions', function () {
    $customer = transactionSummaryUser();

    $this->actingAs($customer)
        ->get(route('customer.transactions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.total_credits', 0)
            ->where('stats.total_debits', 0)
            ->where('stats.total_refunds', 0)
            ->where('stats.this_month_credits', 0)
            ->where('stats.this_month_debits', 0)
            ->where('stats.this_month_refunds', 0));
});
