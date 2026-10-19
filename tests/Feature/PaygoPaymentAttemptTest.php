<?php

use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\PaygoPaymentAttempt;
use App\Models\PaygoVerificationIntent;
use App\Models\PaystackGatewayAccount;
use App\Models\User;
use App\Models\VerificationService;
use App\Services\Paygo\PaygoPaymentService;
use App\Services\Paygo\PaygoVerificationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    throw new RuntimeException('Payment attempt tests require sqlite :memory: before Laravel boots.');
}

beforeEach(function () {
    $this->withoutVite();
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    Http::preventStrayRequests();
    config([
        'services.paystack.secret_key' => 'sk_test_system',
        'services.paystack.public_key' => 'pk_test_system',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.paygo_payment_retries_enabled' => true,
    ]);
});

function paymentAttemptSetup(string $reference = 'APP-90210', bool $identity = false): array
{
    $user = User::factory()->create(['is_active' => true]);
    Customer::create(['user_id' => $user->id, 'paygo_result_reference_system_price' => 300]);
    approveCustomerKyc($user);
    $verification = VerificationService::updateOrCreate(['slug' => $identity ? 'nin' : 'waec-result-fetch'], [
        'name' => $identity ? 'NIN' : 'WAEC',
        'default_price' => 100, 'cost_price' => 50, 'is_active' => true, 'sort_order' => 1,
    ]);
    $service = CustomerPaygoService::create([
        'user_id' => $user->id, 'verification_service_id' => $verification->id,
        'name' => 'PayGo test', 'public_slug' => CustomerPaygoService::generatePublicSlug('PayGo'),
        'verify_secret_hash' => hash('sha256', 'test-secret'), 'price' => 500, 'reference_price' => 500,
        'is_active' => true, 'callback_mode' => 'redirect',
    ]);
    $paygo = app(PaygoVerificationService::class);
    $intent = $identity
        ? $paygo->createIntent($service, ['nin' => '12345678901'])
        : $paygo->createOrFindResultReferenceIntent($service, $reference, ['params' => paymentAttemptParams()]);
    if ($identity) {
        $intent->update(['reference' => $reference]);
    }

    return [$service, $intent, $user];
}

function paymentAttemptParams(): array
{
    return ['txtExamNumber' => '4310516058', 'ExamYear' => '2026', 'ExamType' => 'MAY/JUN',
        'txtPIN' => '123456789012', 'txtCardSerialNo' => 'WRN123456789'];
}

function paymentAttemptLegacy(PaygoVerificationIntent $intent): void
{
    $intent->update([
        'paystack_gateway_owner_type' => 'system', 'paystack_environment' => 'test',
        'paystack_key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_system'),
        'metadata' => array_merge($intent->metadata ?? [], ['paystack_checkout' => [
            'reference' => $intent->reference, 'authorization_url' => 'https://checkout.paystack.test/original',
            'access_code' => 'original', 'initialized_at' => now()->subDay()->toISOString(),
        ]]),
    ]);
}

function paymentAttemptVerifyResponse(string $reference, string $status, int $amount = 50000): array
{
    return ['status' => true, 'data' => [
        'reference' => $reference, 'status' => $status, 'amount' => $amount,
        'currency' => 'NGN', 'domain' => 'test', 'paid_at' => now()->toISOString(), 'channel' => 'card',
    ]];
}

function paymentAttemptInitializeResponse(string $reference): array
{
    return ['status' => true, 'data' => [
        'reference' => $reference, 'authorization_url' => 'https://checkout.paystack.test/'.$reference,
        'access_code' => 'access-'.$reference,
    ]];
}

function paymentAttemptWebhook(array $data, string $secret): array
{
    $body = json_encode(['event' => 'charge.success', 'data' => $data]);

    return [$body, hash_hmac('sha512', $body, $secret)];
}

it('records the first checkout and preserves the portal reference', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(fn ($request) => Http::response(paymentAttemptInitializeResponse($request['reference'])));

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');

    expect($response['success'])->toBeTrue()
        ->and($response['reference'])->toBe('APP-90210')
        ->and($intent->fresh()->reference)->toBe('APP-90210')
        ->and($intent->paymentAttempts()->count())->toBe(1);
    $attempt = $intent->paymentAttempts()->firstOrFail();
    expect($attempt->attempt_number)->toBe(0)->and($attempt->status)->toBe('initialized')
        ->and($attempt->paystack_environment)->toBe('test')
        ->and(data_get($intent->fresh()->metadata, 'paystack_checkout.reference'))->toBe('APP-90210');
    Http::assertSentCount(1);
});

it('imports an ongoing legacy checkout only once and reuses it after verification', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    Http::fake(['*/transaction/verify/*' => Http::response(paymentAttemptVerifyResponse('APP-90210', 'abandoned'))]);
    $payments = app(PaygoPaymentService::class);

    $first = $payments->checkout($service, $intent, 'buyer@example.test');
    $second = $payments->checkout($service, $intent, 'buyer@example.test');

    expect($first['authorization_url'])->toBe('https://checkout.paystack.test/original')
        ->and($second['served_from'])->toBe('cached_checkout')
        ->and($intent->paymentAttempts()->count())->toBe(1)
        ->and($intent->fresh()->reference)->toBe('APP-90210');
    Http::assertSentCount(2);
});

it('creates numbered replacement checkouts only when explicitly requested', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    Http::fake(function ($request) {
        return Http::response(str_contains($request->url(), '/verify/')
            ? paymentAttemptVerifyResponse(basename($request->url()), 'failed')
            : paymentAttemptInitializeResponse($request['reference']));
    });
    $payments = app(PaygoPaymentService::class);
    expect($payments->checkout($service, $intent, 'buyer@example.test')['error_code'])->toBe('PAYMENT_RETRY_REQUIRED');
    expect($intent->paymentAttempts()->count())->toBe(1);

    $first = $payments->checkout($service, $intent, 'buyer@example.test', true);
    $second = $payments->checkout($service, $intent, 'buyer@example.test', true);

    expect($first['reference'])->toBe('APP-90210-paygo-1')
        ->and($second['reference'])->toBe('APP-90210-paygo-2')
        ->and($intent->fresh()->reference)->toBe('APP-90210')
        ->and($intent->paymentAttempts()->count())->toBe(3);
});

it('blocks replacement while any payment attempt is processing', function (string $status) {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    Http::fake(['*/transaction/verify/*' => Http::response(paymentAttemptVerifyResponse('APP-90210', $status))]);

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);

    expect($response['success'])->toBeFalse()->and($response['error_code'])->toBe('PAYMENT_PROCESSING')
        ->and($intent->paymentAttempts()->count())->toBe(1);
    Http::assertSentCount(1);
})->with(['pending', 'ongoing', 'processing']);

it('does not reuse a cached checkout or create another attempt after verification errors', function (int $status) {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    Http::fake(['*/transaction/verify/*' => Http::response(['status' => false, 'message' => 'Unavailable'], $status)]);

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);

    expect($response['success'])->toBeFalse()->and($response['error_code'])->toBe('PAYMENT_STATE_UNKNOWN')
        ->and($intent->paymentAttempts()->count())->toBe(1)
        ->and($intent->fresh()->status)->toBe('pending');
    Http::assertSentCount(1);
})->with([401, 500]);

it('keeps uncertain initialization attempts for reconciliation without incrementing the reference', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/initialize')) {
            throw new ConnectionException('Connection interrupted');
        }

        return Http::response(paymentAttemptVerifyResponse('APP-90210', 'pending'));
    });
    $payments = app(PaygoPaymentService::class);

    $response = $payments->checkout($service, $intent, 'buyer@example.test');
    expect($response['success'])->toBeFalse()
        ->and($intent->paymentAttempts()->firstOrFail()->status)->toBe('unknown');

    expect($payments->checkout($service, $intent, 'buyer@example.test', true)['error_code'])->toBe('PAYMENT_PROCESSING')
        ->and($intent->paymentAttempts()->count())->toBe(1);
});

it('accepts success from the original attempt after a replacement and settles the intent only once', function () {
    [$service, $intent, $user] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $original = app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    $replacement = PaygoPaymentAttempt::factory()->for($intent, 'intent')->create(array_merge($original->only([
        'amount', 'system_price_snapshot', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint',
    ]), ['attempt_number' => 1, 'reference' => 'APP-90210-paygo-1', 'status' => 'initialized']));
    $paygo = app(PaygoVerificationService::class);

    $paygo->completePayment($original->reference, ['amount' => 500, 'reference' => $original->reference]);
    $paygo->completePayment($replacement->reference, ['amount' => 500, 'reference' => $replacement->reference]);
    $paygo->completePayment($replacement->reference, ['amount' => 500, 'reference' => $replacement->reference]);

    expect($intent->fresh()->reference)->toBe('APP-90210')
        ->and($intent->fresh()->metadata['payment_reference'])->toBe('APP-90210')
        ->and($original->fresh()->status)->toBe('success')
        ->and($replacement->fresh()->duplicate_payment_at)->not->toBeNull()
        ->and((float) $user->paygoWallet->balance)->toBe(200.0)
        ->and($user->paygoWallet->transactions()->count())->toBe(1);
});

it('routes a suffixed Paystack callback back to the original intent', function () {
    [$service, $intent] = paymentAttemptSetup(identity: true);
    paymentAttemptLegacy($intent);
    $original = app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    PaygoPaymentAttempt::factory()->for($intent, 'intent')->create(array_merge($original->only([
        'amount', 'system_price_snapshot', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint',
    ]), ['attempt_number' => 1, 'reference' => 'APP-90210-paygo-1', 'status' => 'initialized']));
    Http::fake(['*/transaction/verify/*' => Http::response(paymentAttemptVerifyResponse('APP-90210-paygo-1', 'success'))]);

    $this->get(route('paygo.callback', ['reference' => 'APP-90210-paygo-1']))->assertRedirect();

    expect($intent->fresh()->status)->toBe('paid')
        ->and($intent->fresh()->reference)->toBe('APP-90210')
        ->and($intent->fresh()->metadata['payment_reference'])->toBe('APP-90210-paygo-1');
});

it('accepts customer gateway webhooks and rejects signatures from a different account', function (bool $legacy) {
    [$service, $intent, $user] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $gateway = PaystackGatewayAccount::create([
        'owner_type' => 'customer', 'customer_id' => $user->customer->id, 'environment' => 'test',
        'public_key' => 'pk_test_customer', 'secret_key' => 'sk_test_customer',
        'key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_customer'),
        'is_trusted' => true, 'is_active' => true, 'verification_status' => 'verified',
    ]);
    $intent->update(['paystack_gateway_account_id' => $gateway->id, 'paystack_gateway_owner_type' => 'customer',
        'paystack_key_fingerprint' => $gateway->key_fingerprint]);
    if (! $legacy) {
        app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    }
    [$body, $signature] = paymentAttemptWebhook(['reference' => 'APP-90210', 'amount' => 50000,
        'currency' => 'NGN', 'domain' => 'test', 'channel' => 'card'], 'sk_test_customer');

    $this->call('POST', route('webhook.paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_system'),
    ], $body)->assertStatus(400);
    expect($intent->fresh()->status)->toBe('pending');

    $this->call('POST', route('webhook.paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body)->assertOk();
    expect($intent->fresh()->status)->toBe('paid');
    Http::assertNothingSent();
})->with([true, false]);

it('preserves legacy webhook processing when no attempt row exists', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    [$body, $signature] = paymentAttemptWebhook(['reference' => 'APP-90210', 'amount' => 50000,
        'currency' => 'NGN', 'domain' => 'test', 'channel' => 'card'], 'sk_test_system');
    $this->call('POST', route('webhook.paystack'), [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body)->assertOk();

    expect($intent->fresh()->status)->toBe('paid')->and($intent->paymentAttempts()->count())->toBe(0);
});

it('uses exact reference relationships even when a portal reference already contains a retry suffix', function () {
    [$service, $intent] = paymentAttemptSetup('APP-90210-paygo-1');
    paymentAttemptLegacy($intent);
    app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    $paygo = app(PaygoVerificationService::class);

    expect($paygo->findIntentByPaymentReference('APP-90210-paygo-1')->id)->toBe($intent->id)
        ->and($paygo->findIntentByPaymentReference('APP-90210'))->toBeNull();
});

it('can disable creation of replacements without breaking existing checkout handling', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    config(['services.paystack.paygo_payment_retries_enabled' => false]);
    Http::fake(['*/transaction/verify/*' => Http::response(paymentAttemptVerifyResponse('APP-90210', 'failed'))]);

    expect(app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true)['error_code'])
        ->toBe('PAYMENT_RETRIES_DISABLED');
    expect($intent->paymentAttempts()->count())->toBe(1);
});

it('does not initialize a second transaction when the same intent is submitted during initialization', function () {
    [$service, $intent] = paymentAttemptSetup();
    $nested = null;
    Http::fake(function ($request) use ($service, $intent, &$nested) {
        if (str_contains($request->url(), '/verify/')) {
            return Http::response(['status' => false, 'message' => 'Transaction reference not found'], 404);
        }
        $nested = app(PaygoPaymentService::class)->checkout($service, $intent->fresh(), 'buyer@example.test', true);

        return Http::response(paymentAttemptInitializeResponse($request['reference']));
    });
    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');

    expect($response['success'])->toBeTrue()->and($nested['success'])->toBeFalse()
        ->and($intent->paymentAttempts()->count())->toBe(1);
    Http::assertSentCount(2);
});

it('preserves a webhook success that arrives before initialization finishes', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(function ($request) {
        app(PaygoVerificationService::class)->completePayment($request['reference'], [
            'reference' => $request['reference'], 'amount' => 500,
        ]);

        return Http::response(paymentAttemptInitializeResponse($request['reference']));
    });

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');
    expect($response['paid'])->toBeTrue()->and($intent->fresh()->status)->toBe('paid')
        ->and($intent->paymentAttempts()->firstOrFail()->status)->toBe('success');
});

it('does not switch accounts or environments when the original gateway credentials change', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    config(['services.paystack.secret_key' => 'sk_live_other_account']);
    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);

    expect($response['success'])->toBeFalse()->and($intent->paymentAttempts()->count())->toBe(1);
    Http::assertNothingSent();
});

it('exposes controlled payment retry on the result form and handles the suffixed checkout server side', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $this->get(route('paygo.results.service', ['publicSlug' => $service->public_slug, 'reference' => 'APP-90210']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Public/Paygo/ResultInitiate')->where('prefill.reference', 'APP-90210')->where('paymentRetriesEnabled', true));
    Http::fake(fn ($request) => Http::response(str_contains($request->url(), '/verify/')
        ? paymentAttemptVerifyResponse(basename($request->url()), 'abandoned')
        : paymentAttemptInitializeResponse($request['reference'])));
    $this->withHeaders(['X-Inertia' => 'true'])->post(route('paygo.results.service', $service->public_slug), array_merge(paymentAttemptParams(), [
        'reference' => 'APP-90210', 'email' => 'buyer@example.test', 'retry_payment' => true,
    ]))->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.paystack.test/APP-90210-paygo-1');

    expect($intent->fresh()->reference)->toBe('APP-90210');
});

it('rejects successful verification responses whose details do not match the attempt', function (array $overrides) {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $response = paymentAttemptVerifyResponse('APP-90210', 'success');
    $response['data'] = array_merge($response['data'], $overrides);
    Http::fake(['*/transaction/verify/*' => Http::response($response)]);

    $result = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);
    expect($result['success'])->toBeFalse()->and($intent->fresh()->paid_at)->toBeNull()
        ->and($intent->paymentAttempts()->count())->toBe(1);
})->with([
    'underpayment' => [['amount' => 100]],
    'different currency' => [['currency' => 'USD']],
    'different reference' => [['reference' => 'OTHER-REF']],
    'different environment' => [['domain' => 'live']],
]);

it('reconciles duplicate-reference initialization responses without creating another attempt', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(fn ($request) => str_contains($request->url(), '/initialize')
        ? Http::response(['status' => false, 'message' => 'Duplicate Transaction Reference'], 400)
        : Http::response(paymentAttemptVerifyResponse('APP-90210', 'success')));

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');
    expect($response['paid'])->toBeTrue()->and($intent->fresh()->status)->toBe('paid')
        ->and($intent->paymentAttempts()->count())->toBe(1)
        ->and($intent->paymentAttempts()->firstOrFail()->status)->toBe('success');
    Http::assertSentCount(2);
});

it('reuses the reserved reference after a definitive initialization rejection', function () {
    [$service, $intent] = paymentAttemptSetup();
    $calls = 0;
    Http::fake(function ($request) use (&$calls) {
        $calls++;

        return $calls === 1
            ? Http::response(['status' => false, 'message' => 'Invalid email address'], 400)
            : Http::response(paymentAttemptInitializeResponse($request['reference']));
    });
    $payments = app(PaygoPaymentService::class);
    expect($payments->checkout($service, $intent, 'invalid')['error_code'])->toBe('INITIALIZATION_FAILED');
    $response = $payments->checkout($service, $intent, 'buyer@example.test');

    expect($response['success'])->toBeTrue()->and($response['reference'])->toBe('APP-90210')
        ->and($intent->paymentAttempts()->count())->toBe(1);
});

it('recovers an interrupted initialization with a missing Paystack transaction using the same reference', function () {
    [$service, $intent] = paymentAttemptSetup();
    $payments = app(PaygoPaymentService::class);
    $attempt = $payments->ensureOriginalAttempt($intent);
    $attempt->forceFill(['status' => 'unknown', 'updated_at' => now()->subMinutes(2)])->save();
    Http::fake(fn ($request) => str_contains($request->url(), '/verify/')
        ? Http::response(['status' => false, 'message' => 'Transaction reference not found'], 404)
        : Http::response(paymentAttemptInitializeResponse($request['reference'])));

    $response = $payments->checkout($service, $intent, 'buyer@example.test', true);
    expect($response['success'])->toBeTrue()->and($response['reference'])->toBe('APP-90210')
        ->and($intent->paymentAttempts()->count())->toBe(1);
    Http::assertSentCount(2);
});

it('does not overwrite a payment confirmed during an initialization timeout', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(function ($request) {
        app(PaygoVerificationService::class)->completePayment($request['reference'], [
            'reference' => $request['reference'], 'amount' => 500,
        ]);
        throw new ConnectionException('Response lost after payment confirmation');
    });

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');
    expect($response['paid'])->toBeTrue()->and($intent->fresh()->status)->toBe('paid')
        ->and($intent->paymentAttempts()->firstOrFail()->status)->toBe('success');
});

it('skips suffixes already belonging to another portal reference', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    app(PaygoVerificationService::class)->createOrFindResultReferenceIntent($service, 'APP-90210-paygo-1', [
        'params' => paymentAttemptParams(),
    ]);
    Http::fake(fn ($request) => Http::response(str_contains($request->url(), '/verify/')
        ? paymentAttemptVerifyResponse(basename($request->url()), 'failed')
        : paymentAttemptInitializeResponse($request['reference'])));

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);
    expect($response['reference'])->toBe('APP-90210-paygo-2');
});

it('preserves the original price snapshot while a checkout initialization is unresolved', function () {
    [$service, $intent] = paymentAttemptSetup();
    Http::fake(fn () => throw new ConnectionException('Initialization timeout'));
    app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test');
    $service->update(['reference_price' => 900]);
    $existing = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent($service->fresh(), 'APP-90210', [
        'params' => paymentAttemptParams(),
    ]);

    expect($existing->amount)->toBe('500.00')->and($existing->paymentAttempts()->firstOrFail()->amount)->toBe('500.00');
});

it('accepts suffixed webhooks only once even after the intent has already been consumed', function () {
    [$service, $intent, $user] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $original = app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    $attempt = PaygoPaymentAttempt::factory()->for($intent, 'intent')->create(array_merge($original->only([
        'amount', 'system_price_snapshot', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint',
    ]), ['attempt_number' => 1, 'reference' => 'APP-90210-paygo-1', 'status' => 'initialized']));
    [$body, $signature] = paymentAttemptWebhook(['reference' => $attempt->reference, 'amount' => 50000,
        'currency' => 'NGN', 'domain' => 'test', 'channel' => 'card'], 'sk_test_system');
    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature];
    $this->call('POST', route('webhook.paystack'), [], [], [], $headers, $body)->assertOk();
    $intent->refresh()->update(['status' => 'used', 'reference_fetches' => 2]);
    $this->call('POST', route('webhook.paystack'), [], [], [], $headers, $body)->assertOk();

    expect($intent->fresh()->status)->toBe('used')->and($intent->fresh()->reference_fetches)->toBe(2)
        ->and($user->paygoWallet->transactions()->count())->toBe(1)
        ->and($attempt->fresh()->duplicate_payment_at)->toBeNull();
});

it('blocks a new checkout when an older attempt is still processing even if the latest attempt failed', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $original = app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    PaygoPaymentAttempt::factory()->for($intent, 'intent')->create(array_merge($original->only([
        'amount', 'system_price_snapshot', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint',
    ]), ['attempt_number' => 1, 'reference' => 'APP-90210-paygo-1', 'status' => 'failed']));
    Http::fake(fn ($request) => Http::response(paymentAttemptVerifyResponse(basename($request->url()),
        str_ends_with($request->url(), '/APP-90210') ? 'ongoing' : 'failed')));

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);
    expect($response['error_code'])->toBe('PAYMENT_PROCESSING')->and($intent->paymentAttempts()->count())->toBe(2);
    Http::assertSentCount(1);
});

it('preserves a payment confirmed during a stale verification response', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    Http::fake(function ($request) {
        app(PaygoVerificationService::class)->completePayment('APP-90210', ['reference' => 'APP-90210', 'amount' => 500]);

        return Http::response(paymentAttemptVerifyResponse('APP-90210', 'abandoned'));
    });

    $response = app(PaygoPaymentService::class)->checkout($service, $intent, 'buyer@example.test', true);
    expect($response['paid'])->toBeTrue()->and($intent->fresh()->status)->toBe('paid')
        ->and($intent->paymentAttempts()->firstOrFail()->status)->toBe('success')
        ->and($intent->paymentAttempts()->count())->toBe(1);
});

it('does not mark an intent failed when an older callback fails while a replacement checkout exists', function () {
    [$service, $intent] = paymentAttemptSetup();
    paymentAttemptLegacy($intent);
    $original = app(PaygoPaymentService::class)->ensureOriginalAttempt($intent);
    PaygoPaymentAttempt::factory()->for($intent, 'intent')->create(array_merge($original->only([
        'amount', 'system_price_snapshot', 'paystack_gateway_owner_type', 'paystack_environment', 'paystack_key_fingerprint',
    ]), ['attempt_number' => 1, 'reference' => 'APP-90210-paygo-1', 'status' => 'initialized']));
    Http::fake(['*/transaction/verify/*' => Http::response(paymentAttemptVerifyResponse('APP-90210', 'failed'))]);

    $this->get(route('paygo.callback', ['reference' => 'APP-90210']))->assertRedirect();

    expect($intent->fresh()->status)->toBe('pending')->and($intent->paid_at)->toBeNull();
});
