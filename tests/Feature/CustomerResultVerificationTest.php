<?php

use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createResultCustomer(): User
{
    Role::findOrCreate('customer');

    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->assignRole('customer');
    $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    return $user;
}

function createCustomerResultService(string $slug, string $name): VerificationService
{
    return VerificationService::updateOrCreate(
        ['slug' => $slug],
        [
            'name' => $name,
            'description' => "{$name} test service",
            'default_price' => 100,
            'cost_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ],
    );
}

it('shows one customer card per result board and hides form charge services', function () {
    $customer = createResultCustomer();
    createCustomerResultService('waec-result-form', 'WAEC Result Form');
    createCustomerResultService('waec-result-fetch', 'WAEC Result Fetch');

    $this->actingAs($customer)
        ->get('/customer/verify')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Verification/Index')
            ->has('services')
        );

    $services = $this->actingAs($customer)
        ->get('/customer/verify')
        ->viewData('page')['props']['services'] ?? [];

    expect(collect($services)->pluck('slug'))->toContain('waec-result-fetch')
        ->not->toContain('waec-result-form');
});

it('loads result board form fields internally without charging the customer', function () {
    $customer = createResultCustomer();
    $service = createCustomerResultService('nbais-result-fetch', 'NBAIS Result Fetch');

    $response = $this->actingAs($customer)
        ->getJson("/customer/verify/{$service->id}/form-fields")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.board', 'NBAIS');

    $fieldNames = collect($response->json('data.fields'))->pluck('name');

    expect($fieldNames->all())->toBe(['year', 'month', 'exam_no', 'pin']);

    expect((float) $customer->wallet()->first()->fresh()->balance)->toBe(500.0)
        ->and(VerificationRequest::count())->toBe(0);
});

it('shows the provided inputs on the customer verification result page', function () {
    $customer = createResultCustomer();
    $service = createCustomerResultService('neco-everify-result-fetch', 'NECO e-Verify Result Fetch');

    $verification = VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-INPUTS-001',
        'search_parameter' => '2410896226BC',
        'request_data' => [
            'board' => 'neco-everify',
            'action' => 'fetch',
            'parameters' => [
                'token' => '***REDACTED***',
                'examno' => '2410896226BC',
                'exam_year' => '2024',
                'exam_type' => 'SSCEInt',
            ],
        ],
        'amount_charged' => 100,
        'status' => 'failed',
        'source' => 'web',
        'error_message' => 'Invalid token or token has not been verified!',
        'completed_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('customer.verification.result', $verification))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Verification/Result')
            ->where('providedInputs.token', '***REDACTED***')
            ->where('providedInputs.examno', '2410896226BC')
            ->where('providedInputs.exam_year', '2024')
            ->where('result.error_message', 'Invalid token or token has not been verified!')
        );
});
