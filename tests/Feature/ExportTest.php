<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createUserWithRole(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function createWalletFor(User $user): Wallet
{
    return Wallet::create([
        'user_id' => $user->id,
        'balance' => 5000,
        'bonus_balance' => 500,
        'currency' => 'NGN',
        'is_active' => true,
    ]);
}

function createVerificationService(): VerificationService
{
    return VerificationService::create([
        'name' => 'NIN Lookup',
        'slug' => 'nin-lookup',
        'description' => 'Test service',
        'icon' => 'mdi-card-account-details',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function createVerificationRequestForExport(array $attributes): VerificationRequest
{
    return VerificationRequest::unguarded(fn () => VerificationRequest::create($attributes));
}

it('exports admin transactions with grouped filters applied correctly', function () {
    $admin = createUserWithRole('admin');
    $matchingUser = createUserWithRole('customer');
    $otherUser = createUserWithRole('customer');

    $matchingWallet = createWalletFor($matchingUser);
    $otherWallet = createWalletFor($otherUser);

    $creditTransaction = Transaction::create([
        'user_id' => $matchingUser->id,
        'wallet_id' => $matchingWallet->id,
        'reference' => 'TXN-CREDIT-001',
        'type' => 'credit',
        'category' => 'funding',
        'amount' => 1500,
        'balance_before' => 0,
        'balance_after' => 1500,
        'description' => 'Credit transaction',
        'status' => 'completed',
    ]);

    $debitTransaction = Transaction::create([
        'user_id' => $matchingUser->id,
        'wallet_id' => $matchingWallet->id,
        'reference' => 'TXN-DEBIT-001',
        'type' => 'debit',
        'category' => 'verification',
        'amount' => 100,
        'balance_before' => 1500,
        'balance_after' => 1400,
        'description' => 'Debit transaction',
        'status' => 'completed',
    ]);

    Transaction::create([
        'user_id' => $otherUser->id,
        'wallet_id' => $otherWallet->id,
        'reference' => 'TXN-OTHER-001',
        'type' => 'credit',
        'category' => 'funding',
        'amount' => 500,
        'balance_before' => 0,
        'balance_after' => 500,
        'description' => 'Other transaction',
        'status' => 'completed',
    ]);

    $matchingUser->update(['name' => 'Alice Export']);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.transactions.export', ['search' => 'Alice Export', 'type' => 'credit']));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('TXN-CREDIT-001')
        ->not->toContain($debitTransaction->reference)
        ->not->toContain('TXN-OTHER-001');
});

it('exports admin verifications with search and status filters', function () {
    $admin = createUserWithRole('admin');
    $customer = createUserWithRole('customer');
    $service = createVerificationService();

    VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-COMP-001',
        'search_parameter' => '12345678901',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'completed_at' => now(),
    ]);

    VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-FAIL-001',
        'search_parameter' => '12345678901',
        'amount_charged' => 100,
        'status' => 'failed',
        'source' => 'web',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.verifications.export', ['search' => '12345678901', 'status' => 'completed']));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('VER-COMP-001')
        ->not->toContain('VER-FAIL-001');
});

it('exports customer transactions scoped to the authenticated user', function () {
    $customer = createUserWithRole('customer');
    $otherCustomer = createUserWithRole('customer');

    $wallet = createWalletFor($customer);
    $otherWallet = createWalletFor($otherCustomer);

    Transaction::create([
        'user_id' => $customer->id,
        'wallet_id' => $wallet->id,
        'reference' => 'TXN-MINE-001',
        'type' => 'debit',
        'category' => 'verification',
        'amount' => 100,
        'balance_before' => 1000,
        'balance_after' => 900,
        'description' => 'My verification',
        'status' => 'completed',
    ]);

    Transaction::create([
        'user_id' => $otherCustomer->id,
        'wallet_id' => $otherWallet->id,
        'reference' => 'TXN-THEIRS-001',
        'type' => 'debit',
        'category' => 'verification',
        'amount' => 100,
        'balance_before' => 1000,
        'balance_after' => 900,
        'description' => 'Other verification',
        'status' => 'completed',
    ]);

    $response = $this
        ->actingAs($customer)
        ->get(route('customer.transactions.export', ['category' => 'verification']));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('TXN-MINE-001')
        ->not->toContain('TXN-THEIRS-001');
});

it('exports customer verification history scoped to the authenticated user', function () {
    $customer = createUserWithRole('customer');
    $otherCustomer = createUserWithRole('customer');
    $service = createVerificationService();
    $otherService = VerificationService::create([
        'name' => 'BVN Lookup',
        'slug' => 'bvn-lookup',
        'description' => 'Test service',
        'icon' => 'mdi-card-account-details',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    createVerificationRequestForExport([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-MINE-001',
        'search_parameter' => '11111111111',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'created_at' => '2026-01-10 10:00:00',
        'completed_at' => now(),
    ]);

    createVerificationRequestForExport([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-OUTSIDE-DATE-001',
        'search_parameter' => '33333333333',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'created_at' => '2026-01-20 10:00:00',
        'completed_at' => now(),
    ]);

    createVerificationRequestForExport([
        'user_id' => $customer->id,
        'verification_service_id' => $otherService->id,
        'reference' => 'VER-OTHER-SERVICE-001',
        'search_parameter' => '44444444444',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'created_at' => '2026-01-10 10:00:00',
        'completed_at' => now(),
    ]);

    createVerificationRequestForExport([
        'user_id' => $otherCustomer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-THEIRS-001',
        'search_parameter' => '22222222222',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'created_at' => '2026-01-10 10:00:00',
        'completed_at' => now(),
    ]);

    $response = $this
        ->actingAs($customer)
        ->get(route('customer.verification.export', [
            'service' => $service->id,
            'status' => 'completed',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-15',
        ]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('VER-MINE-001')
        ->toContain('NIN Lookup')
        ->not->toContain('VER-OUTSIDE-DATE-001')
        ->not->toContain('VER-OTHER-SERVICE-001')
        ->not->toContain('VER-THEIRS-001');
});

it('downloads a customer verification result as json and blocks other users', function () {
    $customer = createUserWithRole('customer');
    $otherCustomer = createUserWithRole('customer');
    $service = createVerificationService();

    $verification = VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-DOWNLOAD-001',
        'search_parameter' => '33333333333',
        'response_data' => ['first_name' => 'Ada', 'verified' => true],
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'completed_at' => now(),
    ]);

    $ownResponse = $this
        ->actingAs($customer)
        ->get(route('customer.verification.download', $verification));

    $ownResponse->assertOk();
    $ownResponse->assertHeader('content-type', 'application/json; charset=UTF-8');

    $json = $ownResponse->streamedContent();

    expect($json)
        ->toContain('VER-DOWNLOAD-001')
        ->toContain('Ada');

    $otherResponse = $this
        ->actingAs($otherCustomer)
        ->get(route('customer.verification.download', $verification));

    $otherResponse->assertForbidden();
});
