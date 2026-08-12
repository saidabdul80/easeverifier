<?php

use App\Models\Customer;
use App\Models\CustomerPaystackSplitAccount;
use App\Models\CustomerPaystackSplitLedger;
use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\User;
use App\Models\VerificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createSplitAdmin(): User
{
    Role::findOrCreate('admin');

    $admin = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $admin->assignRole('admin');

    return $admin;
}

function createSplitCustomer(): User
{
    Role::findOrCreate('customer');

    $user = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $user->assignRole('customer');

    Customer::create([
        'user_id' => $user->id,
        'account_type' => 'individual',
        'country' => 'Nigeria',
    ]);

    return $user->fresh('customer');
}

it('allows admin to save up to two paystack split accounts for a customer', function () {
    config([
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        'https://api.paystack.co/subaccount' => Http::sequence()
            ->push([
                'status' => true,
                'data' => [
                    'subaccount_code' => 'ACCT_school',
                    'account_name' => 'School Ltd',
                    'account_number' => '0123456789',
                    'settlement_bank' => 'Test Bank',
                ],
            ])
            ->push([
                'status' => true,
                'data' => [
                    'subaccount_code' => 'ACCT_partner',
                    'account_name' => 'Partner Ltd',
                    'account_number' => '1111111111',
                    'settlement_bank' => 'Second Bank',
                ],
            ]),
    ]);

    $admin = createSplitAdmin();
    $customer = createSplitCustomer();

    $this->actingAs($admin)
        ->post("/admin/customers/{$customer->id}/paystack-splits", [
            'splits' => [
                [
                    'label' => 'School account',
                    'bank_name' => 'Test Bank',
                    'bank_code' => '058',
                    'account_number' => '0123456789',
                    'flat_amount' => 75,
                    'is_active' => true,
                ],
                [
                    'label' => 'Partner account',
                    'bank_name' => 'Second Bank',
                    'bank_code' => '011',
                    'account_number' => '1111111111',
                    'flat_amount' => 25,
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CustomerPaystackSplitAccount::where('customer_id', $customer->customer->id)->count())->toBe(2)
        ->and(CustomerPaystackSplitAccount::where('subaccount_code', 'ACCT_school')->value('flat_amount'))->toBe('75.00')
        ->and(CustomerPaystackSplitAccount::where('subaccount_code', 'ACCT_school')->value('account_number_last4'))->toBe('6789');

    Http::assertSentCount(2);
});

it('rejects more than two paystack split accounts for a customer', function () {
    $admin = createSplitAdmin();
    $customer = createSplitCustomer();

    $this->actingAs($admin)
        ->post("/admin/customers/{$customer->id}/paystack-splits", [
            'splits' => [
                ['bank_name' => 'Test Bank', 'bank_code' => '058', 'account_number' => '0123456789', 'flat_amount' => 10, 'is_active' => true],
                ['bank_name' => 'Second Bank', 'bank_code' => '011', 'account_number' => '1111111111', 'flat_amount' => 20, 'is_active' => true],
                ['bank_name' => 'Third Bank', 'bank_code' => '032', 'account_number' => '2222222222', 'flat_amount' => 30, 'is_active' => true],
            ],
        ])
        ->assertSessionHasErrors('splits');
});

it('returns paystack banks for the admin split form', function () {
    config([
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                ['name' => 'Access Bank', 'code' => '044', 'slug' => 'access-bank'],
                ['name' => 'GTBank', 'code' => '058', 'slug' => 'gtbank'],
            ],
        ]),
    ]);

    $admin = createSplitAdmin();

    $this->actingAs($admin)
        ->getJson('/admin/paystack-banks')
        ->assertOk()
        ->assertJsonPath('banks.0.name', 'Access Bank')
        ->assertJsonPath('banks.1.code', '058');
});

it('shows split ledger to admin and only the owning customer', function () {
    $admin = createSplitAdmin();
    $customer = createSplitCustomer();
    $otherCustomer = createSplitCustomer();
    $service = VerificationService::create([
        'name' => 'NIN Verification',
        'slug' => 'nin',
        'description' => 'NIN service',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $paygoService = CustomerPaygoService::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'name' => 'Candidate NIN',
        'public_slug' => CustomerPaygoService::generatePublicSlug('Candidate NIN'),
        'verify_secret_hash' => hash('sha256', 'pgs_test_secret'),
        'price' => 200,
        'is_active' => true,
    ]);
    $intent = PaygoVerificationIntent::create([
        'customer_paygo_service_id' => $paygoService->id,
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'flow_type' => 'identity',
        'reference' => 'PGO_LEDGER_ONE',
        'nin_hash' => PaygoVerificationIntent::hashNin('12345678901'),
        'lookup_hash' => PaygoVerificationIntent::hashLookup($paygoService->id.':12345678901'),
        'lookup_label' => '****8901',
        'amount' => 200,
        'system_price_snapshot' => 100,
        'status' => 'paid',
        'verification_attempts' => 0,
        'max_fetches_snapshot' => 3,
        'reference_fetches' => 0,
        'metadata' => [],
    ]);

    CustomerPaystackSplitLedger::create([
        'customer_id' => $customer->customer->id,
        'user_id' => $customer->id,
        'paygo_verification_intent_id' => $intent->id,
        'payment_reference' => 'PGO_LEDGER_ONE',
        'split_reference' => 'SPLIT_PGO_LEDGER_ONE',
        'subaccount_code' => 'ACCT_school',
        'subaccount_label' => 'School account',
        'flat_amount' => 75,
        'flat_amount_kobo' => 7500,
        'transaction_amount' => 200,
        'main_account_remainder' => 125,
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/paystack-splits')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PaystackSplits/Index')
            ->where('ledgers.data.0.payment_reference', 'PGO_LEDGER_ONE'));

    $this->actingAs($customer)
        ->get('/customer/paygo-splits')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customer/Paygo/Splits')
            ->where('ledgers.data.0.payment_reference', 'PGO_LEDGER_ONE'));

    $this->actingAs($otherCustomer)
        ->get('/customer/paygo-splits')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customer/Paygo/Splits')
            ->where('ledgers.data', []));
});
