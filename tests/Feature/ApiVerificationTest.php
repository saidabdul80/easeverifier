<?php

use App\Models\ApiKey;
use App\Models\User;
use App\Models\VerificationService;

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
