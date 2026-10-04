<?php

use App\Mcp\Servers\EaseVerifierServer;
use App\Mcp\Tools\GetResultRequirements;
use App\Mcp\Tools\GetVerification;
use App\Mcp\Tools\GetWalletBalance;
use App\Mcp\Tools\ListNbaisSchools;
use App\Mcp\Tools\ListResultPinProducts;
use App\Mcp\Tools\ListServices;
use App\Mcp\Tools\ListVerifications;
use App\Mcp\Tools\PurchaseResultPins;
use App\Mcp\Tools\VerifyIdentity;
use App\Mcp\Tools\VerifyResult;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Transport\FakeTransporter;

beforeEach(function () {
    config([
        'passport.private_key' => file_get_contents(base_path('tests/Fixtures/oauth-private.key')),
        'passport.public_key' => file_get_contents(base_path('tests/Fixtures/oauth-public.key')),
    ]);
});

it('advertises the complete customer api tool set', function () {
    $server = new EaseVerifierServer(new FakeTransporter);

    expect($server->createContext()->tools()->map->name()->all())->toBe([
        'list_services',
        'get_wallet_balance',
        'list_verifications',
        'get_verification',
        'verify_identity',
        'get_result_requirements',
        'verify_result',
        'list_nbais_schools',
        'list_result_pin_products',
        'purchase_result_pins',
    ]);
});

it('marks non charging tools as read only', function (string $toolClass) {
    $annotations = app($toolClass)->annotations();

    expect($annotations)
        ->toMatchArray([
            'readOnlyHint' => true,
            'openWorldHint' => false,
        ])
        ->not->toHaveKey('destructiveHint');
})->with([
    ListServices::class,
    GetWalletBalance::class,
    ListVerifications::class,
    GetVerification::class,
    ListNbaisSchools::class,
    ListResultPinProducts::class,
]);

it('marks wallet charging tools as destructive and not idempotent', function (string $toolClass) {
    /** @var Tool $tool */
    $tool = app($toolClass);
    $annotations = $tool->annotations();

    expect($annotations)
        ->toMatchArray([
            'destructiveHint' => true,
            'openWorldHint' => false,
        ])
        ->not->toHaveKey('idempotentHint');
})->with([
    VerifyIdentity::class,
    GetResultRequirements::class,
    VerifyResult::class,
    PurchaseResultPins::class,
]);

it('advertises oauth on every tool', function () {
    $server = new EaseVerifierServer(new FakeTransporter);

    $server->createContext()->tools()->each(function (Tool $tool): void {
        expect($tool->toArray())->toHaveKey('securitySchemes', [
            [
                'type' => 'oauth2',
                'scopes' => ['mcp:use'],
            ],
        ]);
    });
});

it('requires authentication for the streamable http endpoint', function () {
    $response = $this->postJson('/api/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'EaseVerifier test client',
                'version' => '1.0.0',
            ],
        ],
    ]);

    $response
        ->assertUnauthorized()
        ->assertHeader(
            'WWW-Authenticate',
            'Bearer realm="mcp", resource_metadata="http://localhost/.well-known/oauth-protected-resource/api/mcp"',
        );
});
