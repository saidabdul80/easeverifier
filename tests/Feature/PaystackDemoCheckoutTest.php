<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    config()->set('services.paystack.test_public_key', 'pk_test_documentation');
    config()->set('services.paystack.test_secret_key', 'sk_test_documentation');
});

it('initializes a fixed Paystack test checkout', function () {
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/demo-access',
                'access_code' => 'demo-access',
                'reference' => 'DEMO_1234567890ABCDEF1234',
            ],
        ]),
    ]);

    $initialize = $this->postJson(route('paygo.demo.initialize'))
        ->assertOk()
        ->assertJsonPath('amount', 1500)
        ->assertJsonPath('currency', 'NGN')
        ->assertJsonPath('checkout_url', 'https://checkout.paystack.com/demo-access')
        ->assertJsonStructure(['reference', 'verification_token', 'frame_url']);

    $this->get($initialize->json('frame_url'))
        ->assertOk()
        ->assertSee('https://js.paystack.co/v2/inline.js', false)
        ->assertSee('checkout.resumeTransaction');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request['amount'] === 150000
        && $request['email'] === 'paygo-demo@easeverifier.com'
        && $request['channels'] === ['card']
        && str_starts_with($request['reference'], 'DEMO_')
    );
});

it('advances only after Paystack verifies the matching demo transaction', function () {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/transaction/initialize')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/demo-access',
                    'access_code' => 'demo-access',
                    'reference' => $request['reference'],
                ],
            ]);
        }

        return Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 150000,
                'reference' => basename($request->url()),
                'channel' => 'card',
                'customer' => ['email' => 'paygo-demo@easeverifier.com'],
            ],
        ]);
    });

    $initialize = $this->postJson(route('paygo.demo.initialize'))->assertOk();

    $this->postJson(route('paygo.demo.verify'), [
        'reference' => $initialize->json('reference'),
        'verification_token' => $initialize->json('verification_token'),
    ])
        ->assertOk()
        ->assertJson([
            'complete' => true,
            'status' => 'success',
        ]);
});

it('refuses to expose the demo checkout with non-test credentials', function () {
    config()->set('services.paystack.test_public_key', 'pk_live_example');
    config()->set('services.paystack.test_secret_key', 'sk_live_example');
    Http::fake();

    $this->postJson(route('paygo.demo.initialize'))
        ->assertServiceUnavailable()
        ->assertJsonPath('message', 'Paystack test checkout is not configured.');

    Http::assertNothingSent();
});
