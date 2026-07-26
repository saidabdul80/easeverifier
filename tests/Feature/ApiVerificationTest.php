<?php

use App\Models\ApiKey;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

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

function attachApiProvider(VerificationService $service, array $responseMapping = []): ServiceProvider
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
        'response_mapping' => $responseMapping ?: [
            'nin' => 'data.nin',
            'first_name' => 'data.first_name',
        ],
        'timeout' => 10,
        'priority' => 1,
        'is_active' => true,
        'environment' => 'live',
    ]);
}

it('rejects non-test nin values for test api keys', function () {
    $user = createApiUser();
    createNinService();

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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

it('does not require wallet balance for test api keys without test providers', function () {
    $user = createApiUser();
    createNinService();

    $user->wallet()->create([
        'balance' => 0,
        'bonus_balance' => 0,
    ]);

    $apiKey = ApiKey::generate($user->id, 'Sandbox', 'test');

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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
        'Authorization' => 'Bearer ' . $apiKey->getBearerToken(),
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
