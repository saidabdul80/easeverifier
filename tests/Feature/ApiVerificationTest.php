<?php

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Verification\VerificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createApiUser(): User
{
    return User::factory()->create([
        'is_active' => true,
    ]);
}

function createNinService(): VerificationService
{
    return VerificationService::create([
        'name' => 'NIN Verification',
        'slug' => 'nin',
        'description' => 'Test NIN service',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function createApiVerificationService(string $slug, string $name): VerificationService
{
    return VerificationService::create([
        'name' => $name,
        'slug' => $slug,
        'description' => "Test {$name} service",
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function attachApiProvider(VerificationService $service, ?array $responseMapping = null, string $environment = 'live'): ServiceProvider
{
    return ServiceProvider::create([
        'verification_service_id' => $service->id,
        'name' => 'API Test Provider',
        'base_url' => 'https://provider.test',
        'endpoint' => '/nin',
        'http_method' => 'POST',
        'auth_type' => 'custom',
        'auth_config' => ['headers' => []],
        'request_headers' => [],
        'request_body_template' => ['nin' => '{{search_parameter}}'],
        'response_mapping' => $responseMapping ?? [
            'nin' => 'data.nin',
            'first_name' => 'data.first_name',
        ],
        'timeout' => 10,
        'priority' => 1,
        'is_active' => true,
        'environment' => $environment,
    ]);
}

it('rejects non-test nin values for test api keys', function () {
    $user = createApiUser();
    createNinService();

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'error_code' => 'TEST_NIN_REQUIRED',
        ]);
});

it('allows the dedicated test nin for test api keys', function () {
    $user = createApiUser();
    createNinService();

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '11111111111',
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'sandbox' => true,
        ]);
});

it('accepts service-specific identity request fields', function (string $endpoint, string $field, string $value, string $slug, string $responseField) {
    $user = createApiUser();
    createApiVerificationService($slug, strtoupper(str_replace('-', ' ', $slug)).' Verification');

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson($endpoint, [
        $field => $value,
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath("data.{$responseField}", $value);

    expect(VerificationRequest::query()->latest('id')->value('search_parameter'))->toBe($value);
})->with([
    'bvn' => ['/api/v1/verify/bvn', 'bvn', '22123456789', 'bvn', 'bvn'],
    'cac' => ['/api/v1/verify/cac', 'rc_number', 'RC1234567', 'cac', 'rc_number'],
    'drivers-license' => ['/api/v1/verify/drivers-license', 'license_number', 'ABC123456789', 'drivers-license', 'id'],
]);

it('does not require wallet balance for test api keys without test providers', function () {
    $user = createApiUser();
    createNinService();

    $user->wallet()->create([
        'balance' => 0,
        'bonus_balance' => 0,
    ]);

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '11111111111',
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'sandbox' => true,
        ]);
});

it('does not apply the test nin restriction to live api keys', function () {
    $user = createApiUser();
    createNinService();

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(400)
        ->assertJson([
            'success' => false,
            'error_code' => 'NO_PROVIDER',
        ]);
});

it('does not use test providers for live api keys', function () {
    $user = createApiUser();
    $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    attachApiProvider($service, environment: 'test');

    Http::fake();

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(400)
        ->assertJson([
            'success' => false,
            'error_code' => 'NO_PROVIDER',
        ]);

    Http::assertNothingSent();
});

it('hides provider account funding errors from verification customers and refunds the wallet', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    attachApiProvider($service);

    Http::fake([
        '*' => Http::response([
            'message' => '9 FAILED_PRECONDITION: Insufficient fund or credit facility has expired!',
        ], 400),
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', 'Verification service is temporarily unavailable. Please try again later or contact support.')
        ->assertJsonPath('error_code', 'PROVIDER_ACCOUNT_ERROR');

    expect($response->json('error'))->not->toContain('Insufficient fund')
        ->and((float) $wallet->fresh()->total_balance)->toBe(500.0)
        ->and(VerificationRequest::query()->latest('id')->value('status'))->toBe('failed');
});

it('handles provider id rate limits as failures and refunds the wallet', function (int $status, ?string $message) {
    $user = createApiUser();
    $wallet = $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    attachApiProvider($service);

    Http::fake([
        '*' => Http::response($message === null ? [] : ['message' => $message], $status),
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'PROVIDER_RATE_LIMITED')
        ->assertJsonPath('error', $message ?? 'Too many verification requests. Please wait before trying again.');

    Http::assertSentCount(1);

    $verification = VerificationRequest::query()->latest('id')->firstOrFail();

    expect((float) $wallet->fresh()->total_balance)->toBe(500.0)
        ->and($verification->status)->toBe('failed')
        ->and($wallet->transactions()->where('category', 'refund')->count())->toBe(1);
})->with([
    'provider rejects the id' => [400, 'Too many requests for this ID number in a short time. Please try again after 10mins.'],
    'http rate limit' => [429, null],
    'rate limit inside a successful http response' => [200, 'Too many requests for this ID number in a short time. Please try again after 10mins.'],
]);

it('retries incomplete nin responses once without charging twice', function (array $incompleteData) {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    $provider = attachApiProvider($service);
    $inactiveProvider = attachApiProvider($service);
    $inactiveProvider->update(['is_active' => false, 'endpoint' => '/inactive']);

    Http::fakeSequence()
        ->push(['data' => array_merge(['nin' => '12345678901'], $incompleteData)])
        ->push(['data' => ['nin' => '12345678901', 'first_name' => 'Ada']]);

    $result = app(VerificationEngine::class)->verify($user, $service, '12345678901');

    Http::assertSentCount(2);
    Http::assertNotSent(fn ($request) => $request->url() === $inactiveProvider->full_url);

    $verification = VerificationRequest::query()->sole();

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->getData()['first_name'])->toBe('Ada')
        ->and($verification->status)->toBe('completed')
        ->and($verification->service_provider_id)->toBe($provider->id)
        ->and((float) $wallet->fresh()->total_balance)->toBe(400.0)
        ->and($wallet->transactions()->where('type', 'debit')->count())->toBe(1)
        ->and(ApiLog::query()->where('verification_request_id', $verification->id)->count())->toBe(2);
})->with([
    'missing first name' => [[]],
    'null first name' => [['first_name' => null]],
    'empty first name' => [['first_name' => '']],
    'whitespace first name' => [['first_name' => '   ']],
]);

it('falls back through active nin providers by priority and skips inactive providers', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    $primary = attachApiProvider($service);
    $secondary = attachApiProvider($service);
    $secondary->update(['priority' => 2, 'endpoint' => '/secondary']);
    $tertiary = attachApiProvider($service);
    $tertiary->update(['priority' => 3, 'endpoint' => '/tertiary']);
    $inactive = attachApiProvider($service);
    $inactive->update(['is_active' => false, 'priority' => 0, 'endpoint' => '/inactive']);
    $testProvider = attachApiProvider($service, environment: 'test');
    $testProvider->update(['priority' => 0, 'endpoint' => '/test']);
    $otherService = createApiVerificationService('bvn', 'BVN Verification');
    $unrelatedProvider = attachApiProvider($otherService);
    $unrelatedProvider->update(['endpoint' => '/bvn']);

    Http::fake([
        $primary->full_url => Http::sequence()->push(['data' => ['first_name' => null]])->push(['data' => ['first_name' => null]]),
        $secondary->full_url => Http::sequence()->push(['data' => ['first_name' => '']])->push(['data' => ['first_name' => '']]),
        $tertiary->full_url => Http::sequence()->push(['data' => ['first_name' => null]])->push(['data' => ['nin' => '12345678901', 'first_name' => 'Grace']]),
    ]);
    Http::preventStrayRequests();

    $result = app(VerificationEngine::class)->verify($user, $service, '12345678901');

    Http::assertSentCount(6);
    $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())->all();
    $verification = VerificationRequest::query()->sole();

    expect($urls)->toBe([$primary->full_url, $primary->full_url, $secondary->full_url, $secondary->full_url, $tertiary->full_url, $tertiary->full_url])
        ->and($result->isSuccessful())->toBeTrue()
        ->and($result->getData()['first_name'])->toBe('Grace')
        ->and($verification->service_provider_id)->toBe($tertiary->id)
        ->and($verification->status)->toBe('completed')
        ->and($inactive->fresh()->is_active)->toBeFalse()
        ->and((float) $wallet->fresh()->total_balance)->toBe(400.0)
        ->and($wallet->transactions()->where('type', 'debit')->count())->toBe(1)
        ->and(ApiLog::query()->where('verification_request_id', $verification->id)->count())->toBe(6);
});

it('fails and refunds once when all nin providers return incomplete responses', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    attachApiProvider($service);
    $secondary = attachApiProvider($service);
    $secondary->update(['priority' => 2, 'endpoint' => '/secondary']);
    $inactive = attachApiProvider($service);
    $inactive->update(['is_active' => false, 'endpoint' => '/inactive']);

    Http::fake(['*' => Http::response(['data' => ['nin' => '12345678901', 'first_name' => null]])]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');
    $this->withHeaders(['Authorization' => 'Bearer '.$apiKey->getBearerToken()])
        ->postJson('/api/v1/verify/nin', ['nin' => '12345678901', 'consent' => true])
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error_code', 'INCOMPLETE_PROVIDER_RESPONSE');

    Http::assertSentCount(4);
    Http::assertNotSent(fn ($request) => $request->url() === $inactive->full_url);
    $verification = VerificationRequest::query()->sole();

    expect($verification->status)->toBe('failed')
        ->and($verification->response_data)->toBeNull()
        ->and((float) $wallet->fresh()->total_balance)->toBe(500.0)
        ->and($wallet->transactions()->where('type', 'debit')->count())->toBe(1)
        ->and($wallet->transactions()->where('category', 'refund')->count())->toBe(1);
});

it('uses the next priority nin provider if the incomplete response retry is rate limited', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    $primary = attachApiProvider($service);
    $secondary = attachApiProvider($service);
    $secondary->update(['priority' => 2, 'endpoint' => '/secondary']);

    Http::fake([
        $primary->full_url => Http::sequence()
            ->push(['data' => ['first_name' => null]])
            ->push(['message' => 'Too many requests'], 429),
        $secondary->full_url => Http::response(['data' => ['nin' => '12345678901', 'first_name' => 'Ada']]),
    ]);
    Http::preventStrayRequests();

    $result = app(VerificationEngine::class)->verify($user, $service, '12345678901');

    Http::assertSentCount(3);
    expect($result->isSuccessful())->toBeTrue()
        ->and(VerificationRequest::query()->sole()->service_provider_id)->toBe($secondary->id)
        ->and((float) $wallet->fresh()->total_balance)->toBe(400.0);
});

it('uses nin retry and priority fallback through the customer form with one branch wallet charge', function () {
    $this->withoutVite();
    Role::findOrCreate('customer');
    $user = createApiUser();
    $user->assignRole('customer');
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $branch = $user->branches()->create(['name' => 'Verification Branch']);
    $branch->wallet->update(['balance' => 300]);
    $service = createNinService();
    $primary = attachApiProvider($service);
    $secondary = attachApiProvider($service);
    $secondary->update(['priority' => 2, 'endpoint' => '/secondary']);

    Http::fake([
        $primary->full_url => Http::response(['data' => ['nin' => '12345678901', 'first_name' => null]]),
        $secondary->full_url => Http::response(['data' => ['nin' => '12345678901', 'first_name' => 'Ada']]),
    ]);

    $this->actingAs($user)
        ->post("/customer/verify/{$service->id}", ['search_parameter' => '12345678901', 'branch_id' => $branch->id])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Verification/Result')
            ->where('result.success', true)
            ->where('result.data.first_name', 'Ada'));

    Http::assertSentCount(3);
    $verification = VerificationRequest::query()->sole();

    expect($verification->service_provider_id)->toBe($secondary->id)
        ->and($verification->branch_id)->toBe($branch->id)
        ->and((float) $wallet->fresh()->total_balance)->toBe(500.0)
        ->and((float) $branch->wallet->fresh()->total_balance)->toBe(200.0)
        ->and($branch->wallet->transactions()->where('type', 'debit')->count())->toBe(1);
});

it('does not retry provider errors or use inactive providers', function (int $status, string $message) {
    $user = createApiUser();
    $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    attachApiProvider($service);
    $inactive = attachApiProvider($service);
    $inactive->update(['is_active' => false, 'endpoint' => '/inactive']);

    Http::fake(['*' => Http::response(['message' => $message], $status)]);

    $result = app(VerificationEngine::class)->verify($user, $service, '12345678901');

    Http::assertSentCount(1);
    expect($result->isSuccessful())->toBeFalse();
})->with([
    'rate limited' => [429, 'Too many requests'],
    'provider failure' => [500, 'Provider unavailable'],
    'record not found' => [200, 'Record not found'],
]);

it('does not use live providers when a sandbox nin response is incomplete', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    $testProvider = attachApiProvider($service, environment: 'test');
    $testProvider->update(['endpoint' => '/test']);
    attachApiProvider($service);
    $inactive = attachApiProvider($service);
    $inactive->update(['is_active' => false, 'endpoint' => '/inactive']);

    Http::fake([$testProvider->full_url => Http::response(['data' => ['first_name' => null]])]);
    Http::preventStrayRequests();

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');
    $this->withHeaders(['Authorization' => 'Bearer '.$apiKey->getBearerToken()])
        ->postJson('/api/v1/verify/nin', ['nin' => '11111111111', 'consent' => true])
        ->assertStatus(400)
        ->assertJsonPath('error_code', 'INCOMPLETE_PROVIDER_RESPONSE');

    Http::assertSentCount(2);
    expect((float) $wallet->fresh()->total_balance)->toBe(500.0)
        ->and($wallet->transactions()->count())->toBe(0);
});

it('does not require a first name or retry successful responses for other services', function () {
    $user = createApiUser();
    $wallet = $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createApiVerificationService('bvn', 'BVN Verification');
    attachApiProvider($service, ['bvn' => 'data.bvn']);
    $inactive = attachApiProvider($service);
    $inactive->update(['is_active' => false, 'endpoint' => '/inactive']);

    Http::fake(['*' => Http::response(['data' => ['bvn' => '12345678901']])]);

    $result = app(VerificationEngine::class)->verify($user, $service, '12345678901');

    Http::assertSentCount(1);
    expect($result->isSuccessful())->toBeTrue()
        ->and((float) $wallet->fresh()->total_balance)->toBe(400.0);
});

it('does not reuse previously completed nin responses with an empty first name', function () {
    $user = createApiUser();
    $user->wallet()->create(['balance' => 500, 'bonus_balance' => 0]);
    $service = createNinService();
    $provider = attachApiProvider($service);
    VerificationRequest::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'service_provider_id' => $provider->id,
        'reference' => VerificationRequest::generateReference(),
        'search_parameter' => '12345678901',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'api',
        'response_data' => ['nin' => '12345678901', 'first_name' => null],
        'completed_at' => now(),
    ]);

    Http::fake(['*' => Http::response(['data' => ['nin' => '12345678901', 'first_name' => 'Ada']])]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');
    $this->withHeaders(['Authorization' => 'Bearer '.$apiKey->getBearerToken()])
        ->postJson('/api/v1/verify/nin', ['nin' => '12345678901', 'consent' => true])
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada');

    Http::assertSentCount(1);
    expect(VerificationRequest::query()->count())->toBe(2);
});

it('does not reuse cached verification data after provider mapping changes', function () {
    $user = createApiUser();
    $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    $provider = attachApiProvider($service, [
        'nin' => 'data.nin',
        'first_name' => 'data.first_name',
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    Http::fakeSequence()
        ->push([
            'data' => [
                'nin' => '12345678901',
                'first_name' => 'Ada',
            ],
        ], 200)
        ->push([
            'data' => [
                'nin' => '12345678901',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
            ],
        ], 200);

    $firstResponse = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $firstResponse
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('sandbox', false)
        ->assertJsonMissingPath('data._raw')
        ->assertJsonMissingPath('data._sandbox')
        ->assertJsonMissingPath('cached')
        ->assertJsonMissingPath('cached_reference');

    Http::assertSentCount(1);

    Carbon::setTestNow(now()->addMinute());
    $provider->update([
        'response_mapping' => [
            'nin' => 'data.nin',
            'first_name' => 'data.first_name',
            'last_name' => 'data.last_name',
        ],
    ]);
    Carbon::setTestNow();

    $secondResponse = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $secondResponse
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.last_name', 'Lovelace')
        ->assertJsonPath('sandbox', false)
        ->assertJsonMissingPath('data._raw')
        ->assertJsonMissingPath('data._sandbox')
        ->assertJsonMissingPath('cached')
        ->assertJsonMissingPath('cached_reference');

    Http::assertSentCount(2);
});

it('falls back to raw provider response when response mapping is empty', function () {
    $user = createApiUser();
    $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    attachApiProvider($service, []);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $rawResponse = [
        'success' => true,
        'statusCode' => 200,
        'message' => 'Record found',
        'data' => [
            'data' => [
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'birthdate' => '1990-12-10',
                'mobile' => '08012345678',
                'nin' => '12345678901',
            ],
        ],
    ];

    Http::fake([
        '*' => Http::response($rawResponse, 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '12345678901',
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.data.data.firstname', 'Ada')
        ->assertJsonPath('data.data.data.lastname', 'Lovelace');

    $verification = VerificationRequest::query()->latest('id')->first();

    expect($verification)->not->toBeNull()
        ->and($verification->status)->toBe('completed')
        ->and($verification->response_data)->toBe($rawResponse);
});

it('falls back to raw provider response when response mapping resolves only null values', function () {
    $user = createApiUser();
    $user->wallet()->create([
        'balance' => 500,
        'bonus_balance' => 0,
    ]);

    $service = createNinService();
    attachApiProvider($service, [
        'first_name' => 'data.missing_first_name',
        'last_name' => 'data.missing_last_name',
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $rawResponse = [
        'success' => true,
        'statusCode' => 200,
        'message' => 'Record found',
        'data' => [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'nin' => '98765432109',
        ],
    ];

    Http::fake([
        '*' => Http::response($rawResponse, 200),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/verify/nin', [
        'nin' => '98765432109',
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.data.first_name', 'Grace')
        ->assertJsonPath('data.data.last_name', 'Hopper');

    $verification = VerificationRequest::query()->latest('id')->first();

    expect($verification)->not->toBeNull()
        ->and($verification->status)->toBe('completed')
        ->and($verification->response_data)->toBe($rawResponse);
});

it('returns customer pricing when listing services', function () {
    $user = createApiUser();
    $service = createNinService();
    $user->customPricing()->create([
        'verification_service_id' => $service->id,
        'price' => 75,
        'is_active' => true,
    ]);

    $apiKey = ApiKey::generate($user->id, 'Production', 'live');

    $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->getJson('/api/v1/services')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'nin')
        ->assertJsonPath('data.0.price', 75)
        ->assertJsonPath('data.0.currency', 'NGN');
});
