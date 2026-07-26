<?php

use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationService;
use App\Services\Paygo\PaygoVerificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createPaygoCustomer(): User
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

    return $user->fresh('wallet');
}

function createPaygoNinService(float $price = 100): VerificationService
{
    return VerificationService::create([
        'name' => 'NIN Verification',
        'slug' => 'nin',
        'description' => 'NIN service',
        'default_price' => $price,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function attachSuccessfulPaygoProvider(VerificationService $service): void
{
    ServiceProvider::create([
        'verification_service_id' => $service->id,
        'name' => 'PayGo Test Provider',
        'base_url' => 'https://provider.test',
        'endpoint' => '/nin',
        'http_method' => 'POST',
        'auth_type' => 'custom',
        'auth_config' => ['headers' => []],
        'request_headers' => [],
        'request_body_template' => ['nin' => '{{search_parameter}}'],
        'response_mapping' => [
            'nin' => 'data.nin',
            'first_name' => 'data.first_name',
            'last_name' => 'data.last_name',
        ],
        'timeout' => 10,
        'priority' => 1,
        'is_active' => true,
        'environment' => 'live',
    ]);
}

function createPaygoServiceFor(User $user, VerificationService $service, string $secret = 'pgs_test_secret', float $price = 150): CustomerPaygoService
{
    return CustomerPaygoService::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'name' => 'Candidate NIN',
        'public_slug' => CustomerPaygoService::generatePublicSlug('Candidate NIN'),
        'verify_secret_hash' => hash('sha256', $secret),
        'price' => $price,
        'is_active' => true,
    ]);
}

it('does not allow a customer to create a paygo service at or below system price', function () {
    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);

    $response = $this
        ->actingAs($user)
        ->post('/customer/paygo-services', [
            'name' => 'Candidate NIN',
            'verification_service_id' => $service->id,
            'price' => 100,
        ]);

    $response->assertSessionHasErrors('price');
    expect(CustomerPaygoService::count())->toBe(0);
});

it('completes paygo payment idempotently and credits the customer wallet once', function () {
    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'nin' => '12345678901',
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    expect((float) $user->wallet->fresh()->balance)->toBe(150.0)
        ->and($intent->fresh()->status)->toBe('paid');
});

it('rejects an unpaid paygo verification even without requiring a secret', function () {
    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service);
    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'nin' => '12345678901',
    ]);

    $response = $this->postJson("/api/paygo/{$paygoService->public_slug}/verify", [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('error_code', 'PAYGO_PAYMENT_INVALID');
});

it('allows three successful calls for one paid nin and caches after the first', function () {
    Http::fake([
        '*' => Http::response([
            'data' => [
                'nin' => '12345678901',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
            ],
        ], 200),
    ]);

    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);
    attachSuccessfulPaygoProvider($service);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'nin' => '12345678901',
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $response = $this->getJson("/api/paygo/{$paygoService->public_slug}/verify?".http_build_query([
        'nin' => '12345678901',
        'consent' => true,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonMissingPath('data._raw')
        ->assertJsonMissingPath('data._sandbox')
        ->assertJsonMissingPath('cached')
        ->assertJsonMissingPath('cached_reference');

    $response->assertJsonPath('attempts_remaining', 2);

    expect($intent->fresh()->status)->toBe('paid')
        ->and($intent->fresh()->verification_attempts)->toBe(1)
        ->and((float) $user->wallet->fresh()->balance)->toBe(50.0);

    $secondResponse = $this->postJson("/api/paygo/{$paygoService->public_slug}/verify", [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $secondResponse
        ->assertOk()
        ->assertJsonMissingPath('cached')
        ->assertJsonMissingPath('cached_reference')
        ->assertJsonPath('attempts_remaining', 1);

    expect($intent->fresh()->status)->toBe('paid')
        ->and($intent->fresh()->verification_attempts)->toBe(2);

    $thirdResponse = $this->postJson("/api/paygo/{$paygoService->public_slug}/verify", [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $thirdResponse
        ->assertOk()
        ->assertJsonMissingPath('cached')
        ->assertJsonMissingPath('cached_reference')
        ->assertJsonPath('attempts_remaining', 0);

    expect($intent->fresh()->status)->toBe('used')
        ->and($intent->fresh()->verification_attempts)->toBe(3);

    $fourthResponse = $this
        ->postJson("/api/paygo/{$paygoService->public_slug}/verify", [
            'nin' => '12345678901',
            'consent' => true,
        ]);

    $fourthResponse
        ->assertStatus(400)
        ->assertJsonPath('error_code', 'PAYGO_PAYMENT_INVALID');
});
