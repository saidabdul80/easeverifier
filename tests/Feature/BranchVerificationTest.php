<?php

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ServiceProvider;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createBranchCustomer(array $userAttributes = [], array $customerAttributes = []): User
{
    Role::findOrCreate('customer');

    $user = User::factory()->create(array_merge([
        'is_active' => true,
    ], $userAttributes));

    $user->assignRole('customer');

    Customer::create(array_merge([
        'user_id' => $user->id,
        'company_name' => 'Branch Test Company',
    ], $customerAttributes));

    return $user->fresh(['wallet']);
}

function createVerificationServiceWithProvider(array $serviceAttributes = [], array $providerAttributes = []): VerificationService
{
    $service = VerificationService::create(array_merge([
        'name' => 'NIN Verification',
        'slug' => 'nin',
        'description' => 'Branch verification test service',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ], $serviceAttributes));

    ServiceProvider::create(array_merge([
        'verification_service_id' => $service->id,
        'name' => 'Test Provider',
        'base_url' => 'https://provider.test',
        'endpoint' => '/nin',
        'http_method' => 'POST',
        'auth_type' => 'custom',
        'auth_config' => ['headers' => []],
        'request_headers' => [],
        'request_body_template' => ['search' => '{{search_parameter}}'],
        'response_mapping' => [
            'nin' => 'data.nin',
            'first_name' => 'data.first_name',
            'last_name' => 'data.last_name',
        ],
        'timeout' => 10,
        'priority' => 1,
        'is_active' => true,
        'environment' => 'live',
    ], $providerAttributes));

    return $service;
}

it('creates a wallet automatically when a customer creates a branch', function () {
    $customer = createBranchCustomer();

    $response = $this
        ->actingAs($customer)
        ->post(route('customer.branches.store'), [
            'name' => 'Abuja Branch',
            'contact_email' => 'abuja@example.com',
        ]);

    $response->assertRedirect();

    $branch = Branch::firstOrFail();

    expect($branch->user_id)->toBe($customer->id)
        ->and($branch->wallet)->not->toBeNull()
        ->and((float) $branch->wallet->total_balance)->toBe(0.0);

    $this->assertDatabaseHas('wallets', [
        'user_id' => $customer->id,
        'branch_id' => $branch->id,
    ]);
});

it('charges the selected branch wallet for customer web verifications', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'nin' => '22334455667',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
            ],
        ], 200),
    ]);

    $customer = createBranchCustomer();
    $service = createVerificationServiceWithProvider();
    $customer->wallet->update(['balance' => 900]);

    $branch = $customer->branches()->create(['name' => 'Ikeja Branch']);
    $branch->wallet->update(['balance' => 300]);

    $response = $this
        ->actingAs($customer)
        ->post("/customer/verify/{$service->id}", [
            'search_parameter' => '22334455667',
            'branch_id' => $branch->id,
        ]);

    $response->assertOk();

    $branch->wallet->refresh();
    $customer->wallet->refresh();

    expect((float) $branch->wallet->balance)->toBe(200.0)
        ->and((float) $customer->wallet->balance)->toBe(900.0);

    $verification = VerificationRequest::firstOrFail();
    $transaction = Transaction::firstOrFail();

    expect($verification->branch_id)->toBe($branch->id)
        ->and($verification->source)->toBe('web')
        ->and($verification->status)->toBe('completed')
        ->and($transaction->wallet_id)->toBe($branch->wallet->id);
});

it('charges the branch wallet and logs the branch when a branch api key is used', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'nin' => '55667788990',
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
            ],
        ], 200),
    ]);

    $customer = createBranchCustomer();
    $service = createVerificationServiceWithProvider();
    $customer->wallet->update(['balance' => 1000]);

    $branch = $customer->branches()->create(['name' => 'Port Harcourt Branch']);
    $branch->wallet->update(['balance' => 450]);

    $apiKey = ApiKey::generate($customer->id, 'PH Branch Key', 'live', $branch->id);

    $response = $this
        ->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
        ])
        ->postJson('/api/v1/verify/nin', [
            'nin' => '55667788990',
            'consent' => true,
        ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);

    $branch->wallet->refresh();
    $customer->wallet->refresh();

    expect((float) $branch->wallet->balance)->toBe(350.0)
        ->and((float) $customer->wallet->balance)->toBe(1000.0);

    $verification = VerificationRequest::firstOrFail();

    expect($verification->branch_id)->toBe($branch->id)
        ->and($verification->source)->toBe('api')
        ->and(ApiLog::where('branch_id', $branch->id)->count())->toBe(2);
});

it('transfers funds between the primary wallet and a branch wallet', function () {
    $customer = createBranchCustomer();
    $customer->wallet->update(['balance' => 700]);

    $branch = $customer->branches()->create(['name' => 'Enugu Branch']);

    $response = $this
        ->actingAs($customer)
        ->post(route('customer.branches.transfer'), [
            'from_branch_id' => null,
            'to_branch_id' => $branch->id,
            'amount' => 250,
        ]);

    $response->assertRedirect();

    $customer->wallet->refresh();
    $branch->wallet->refresh();

    expect((float) $customer->wallet->balance)->toBe(450.0)
        ->and((float) $branch->wallet->balance)->toBe(250.0)
        ->and(Transaction::where('category', 'adjustment')->count())->toBe(2);
});
