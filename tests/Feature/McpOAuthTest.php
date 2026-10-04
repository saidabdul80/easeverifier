<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'passport.private_key' => file_get_contents(base_path('tests/Fixtures/oauth-private.key')),
        'passport.public_key' => file_get_contents(base_path('tests/Fixtures/oauth-public.key')),
    ]);
});

it('publishes protected resource metadata for the mcp endpoint', function () {
    $this->getJson('/.well-known/oauth-protected-resource/api/mcp')
        ->assertOk()
        ->assertJson([
            'resource' => 'http://localhost/api/mcp',
            'authorization_servers' => ['http://localhost/api/mcp'],
            'scopes_supported' => ['mcp:use'],
        ]);
});

it('publishes oauth authorization server metadata for chatgpt', function () {
    $this->getJson('/.well-known/oauth-authorization-server/api/mcp')
        ->assertOk()
        ->assertJson([
            'issuer' => 'http://localhost/api/mcp',
            'authorization_endpoint' => 'http://localhost/oauth/authorize',
            'token_endpoint' => 'http://localhost/oauth/token',
            'registration_endpoint' => 'http://localhost/oauth/register',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['mcp:use'],
        ]);
});

it('restricts dynamic oauth clients to configured callback domains', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Untrusted Client',
        'redirect_uris' => ['https://untrusted.example/callback'],
    ])->assertUnprocessable();

    $this->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
        'grant_types' => ['authorization_code', 'refresh_token'],
        'response_types' => ['code'],
        'token_endpoint_auth_method' => 'none',
        'scope' => 'mcp:use',
    ])->assertCreated()
        ->assertJson([
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
            'scope' => 'mcp:use',
            'token_endpoint_auth_method' => 'none',
        ])
        ->assertJsonStructure(['client_id', 'client_id_issued_at']);

    $this->postJson('/oauth/register', [
        'client_name' => 'Unsupported Client',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/test-callback'],
        'grant_types' => ['implicit'],
    ])->assertUnprocessable();
});

it('completes the oauth authorization code flow with pkce', function () {
    Role::create(['name' => 'customer']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('customer');
    Customer::create([
        'user_id' => $user->id,
        'company_name' => 'Northfield Academy',
        'api_enabled' => true,
    ]);

    $redirectUri = 'https://chatgpt.com/connector/oauth/easeverifier-test';
    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => [$redirectUri],
    ])->json('client_id');

    $verifier = str_repeat('a', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $authorizationQuery = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'oauth-test-state',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'resource' => 'http://localhost/api/mcp',
    ]);

    $authorization = $this->actingAs($user)->get('/oauth/authorize?'.$authorizationQuery);
    $authorization->assertOk()->assertSee('Connect ChatGPT to EaseVerifier');

    preg_match('/name="auth_token" value="([^"]+)"/', $authorization->getContent(), $matches);
    expect($matches[1] ?? null)->not->toBeNull();

    $approval = $this->actingAs($user)->post('/oauth/authorize', [
        'client_id' => $clientId,
        'auth_token' => $matches[1],
    ]);

    $approval->assertRedirectContains($redirectUri);
    parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $callbackQuery);

    expect($callbackQuery['state'] ?? null)->toBe('oauth-test-state');
    expect($callbackQuery['code'] ?? null)->not->toBeNull();

    $token = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $callbackQuery['code'],
        'resource' => 'http://localhost/api/mcp',
    ]);

    $token->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

    $this->withToken($token->json('access_token'))->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'ChatGPT', 'version' => '1.0.0'],
        ],
    ])->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'EaseVerifier');
});

it('accepts an oauth linked customer with the mcp scope', function () {
    Role::create(['name' => 'customer']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('customer');
    Customer::create([
        'user_id' => $user->id,
        'company_name' => 'Northfield Academy',
        'api_enabled' => true,
    ]);

    Passport::actingAs($user, ['mcp:use']);

    $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'ChatGPT',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('jsonrpc', '2.0')
        ->assertJsonPath('id', 1)
        ->assertJsonPath('result.serverInfo.name', 'EaseVerifier');
});

it('rejects oauth tokens without the mcp scope', function () {
    Role::create(['name' => 'customer']);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('customer');
    Customer::create([
        'user_id' => $user->id,
        'company_name' => 'Northfield Academy',
        'api_enabled' => true,
    ]);

    Passport::actingAs($user);

    $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [],
    ])->assertUnauthorized()
        ->assertJsonPath('error_code', 'INSUFFICIENT_SCOPE');
});
