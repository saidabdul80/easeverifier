<?php

use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationService;
use App\Services\Paygo\PaygoVerificationService;
use App\Services\ResultVerify\ResultGates\WAECResult;
use App\Services\ResultVerify\ResultInterface;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

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
    return VerificationService::updateOrCreate(
        ['slug' => 'nin'],
        [
            'name' => 'NIN Verification',
            'description' => 'NIN service',
            'default_price' => $price,
            'cost_price' => 50,
            'is_active' => true,
            'sort_order' => 1,
        ],
    );
}

function createPaygoResultService(string $slug = 'waec-result-fetch', float $price = 100): VerificationService
{
    return VerificationService::updateOrCreate(
        ['slug' => $slug],
        [
            'name' => ucwords(str_replace('-', ' ', $slug)),
            'description' => 'Result service',
            'default_price' => $price,
            'cost_price' => 0,
            'is_active' => true,
            'sort_order' => 10,
        ],
    );
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
        'callback_mode' => 'redirect',
        'webhook_secret' => CustomerPaygoService::generateWebhookSecret(),
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

    expect((float) $user->paygoWallet()->first()->fresh()->balance)->toBe(50.0)
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
        ->and((float) $user->paygoWallet()->first()->fresh()->balance)->toBe(50.0);

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

it('allows a customer to publish a paygo result verification page', function () {
    $this->withoutVite();

    $user = createPaygoCustomer();
    $service = createPaygoResultService();

    $this
        ->actingAs($user)
        ->post('/customer/paygo-services', [
            'name' => 'WAEC PayGo',
            'verification_service_id' => $service->id,
            'price' => 150,
            'success_url' => 'https://school.test/verify/success',
            'failure_url' => 'https://school.test/verify/failure',
        ])
        ->assertSessionHasNoErrors();

    $paygoService = CustomerPaygoService::firstOrFail();

    $this
        ->get("/paygo/results/customer/{$user->customer->referral_code}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Paygo/ResultInitiate')
            ->has('services', 5)
        );

    $this
        ->get("/paygo/results/{$paygoService->public_slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Public/Paygo/ResultInitiate')
            ->where('paygoService.board', 'WAEC')
            ->where('fields.0.name', 'txtExamNumber')
        );
});

it('exposes one generic result verification option when customers create paygo services', function () {
    $this->withoutVite();

    $user = createPaygoCustomer();
    createPaygoNinService();

    $response = $this
        ->actingAs($user)
        ->get('/customer/paygo-services')
        ->assertOk();

    $options = collect($response->viewData('page')['props']['verificationServices'] ?? []);

    expect($options->pluck('slug')->all())->toContain('nin', 'result-verification')
        ->not->toContain('neco-everify-result-fetch');

    $this
        ->actingAs($user)
        ->post('/customer/paygo-services', [
            'verification_service_id' => 'result',
            'price' => 150,
            'response_mode' => 'redirect',
            'success_url' => 'https://school.test/verify/success',
            'failure_url' => 'https://school.test/verify/failure',
        ])
        ->assertSessionHasNoErrors();

    $resultFetchServices = VerificationService::where('slug', 'like', '%-result-fetch')->count();

    expect(CustomerPaygoService::where('user_id', $user->id)->count())->toBe($resultFetchServices);
});

it('stores a paid paygo result and limits open endpoint pulls by admin configuration', function () {
    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_fetch_limit' => 2]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    app()->instance(WAECResult::class, new class implements ResultInterface
    {
        public function formFields(): array
        {
            return [
                ['name' => 'txtExamNumber', 'label' => 'Examination Number', 'type' => 'text', 'required' => true],
                ['name' => 'ExamYear', 'label' => 'Examination Year', 'type' => 'text', 'required' => true],
                ['name' => 'ExamType', 'label' => 'Examination Type', 'type' => 'text', 'required' => true],
                ['name' => 'txtPIN', 'label' => 'PIN', 'type' => 'text', 'required' => true],
                ['name' => 'txtCardSerialNo', 'label' => 'Card Serial Number', 'type' => 'text', 'required' => true],
            ];
        }

        public function fetchResult(array $params): string
        {
            return '<html>paid result</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'Paid Candidate',
                    'exam_number' => '1234567890',
                ],
                'subjects' => [
                    ['subject' => 'MATHEMATICS', 'grade' => 'A1', 'score' => null],
                ],
                'overall' => null,
            ];
        }
    });

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'params' => [
            'txtExamNumber' => '1234567890',
            'ExamYear' => '2025',
            'ExamType' => 'MAY/JUN',
            'txtPIN' => '123456789012',
            'txtCardSerialNo' => 'WRN123456789',
        ],
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $result = app(PaygoVerificationService::class)->fetchResultForPaidIntent($intent->fresh(), '127.0.0.1');

    expect($result['success'])->toBeTrue()
        ->and($intent->fresh()->max_fetches_snapshot)->toBe(2)
        ->and($intent->fresh()->verificationRequest->response_data['candidate']['name'])->toBe('Paid Candidate');

    $this->getJson("/api/paygo/results/{$intent->reference}")
        ->assertOk()
        ->assertJsonPath('data.candidate.name', 'Paid Candidate')
        ->assertJsonPath('fetches_remaining', 1);

    $this->getJson("/api/paygo/results/{$intent->reference}")
        ->assertOk()
        ->assertJsonPath('fetches_remaining', 0);

    $this->getJson("/api/paygo/results/{$intent->reference}")
        ->assertStatus(429)
        ->assertJsonPath('error_code', 'PULL_LIMIT_EXCEEDED');
});

it('redirects back to the school portal and posts a webhook for hybrid paygo result callbacks', function () {
    $user = createPaygoCustomer();
    $user->customer->update([
        'webhook_url' => 'https://school.test/hooks/easeverifier',
    ]);

    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);
    $paygoService->update([
        'name' => 'WAEC Result Verification',
        'success_url' => 'https://school.test/verify/success',
        'failure_url' => 'https://school.test/verify/failure',
        'callback_mode' => 'hybrid',
    ]);

    app()->instance(WAECResult::class, new class implements ResultInterface
    {
        public function formFields(): array
        {
            return [
                ['name' => 'txtExamNumber', 'label' => 'Examination Number', 'type' => 'text', 'required' => true],
                ['name' => 'ExamYear', 'label' => 'Examination Year', 'type' => 'text', 'required' => true],
                ['name' => 'ExamType', 'label' => 'Examination Type', 'type' => 'text', 'required' => true],
                ['name' => 'txtPIN', 'label' => 'PIN', 'type' => 'text', 'required' => true],
                ['name' => 'txtCardSerialNo', 'label' => 'Card Serial Number', 'type' => 'text', 'required' => true],
            ];
        }

        public function fetchResult(array $params): string
        {
            return '<html>hybrid result</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'Hybrid Candidate',
                    'exam_number' => '1234567890',
                ],
                'subjects' => [
                    ['subject' => 'ENGLISH', 'grade' => 'A1', 'score' => null],
                ],
            ];
        }
    });

    Http::fake([
        '*/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 15000,
                'reference' => 'PGO-HYBRID-REF',
                'paid_at' => now()->toISOString(),
                'channel' => 'card',
                'customer' => [
                    'email' => $user->email,
                ],
            ],
        ], 200),
        'https://school.test/hooks/easeverifier' => Http::response([
            'received' => true,
        ], 200),
    ]);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService->fresh('user.customer', 'verificationService'), [
        'params' => [
            'txtExamNumber' => '1234567890',
            'ExamYear' => '2025',
            'ExamType' => 'MAY/JUN',
            'txtPIN' => '123456789012',
            'txtCardSerialNo' => 'WRN123456789',
        ],
        'portal_context' => [
            'candidate_id' => 'STU-12345',
            'portal_ref' => 'APP-90210',
            'state' => 'signed-state-token',
        ],
    ]);

    $response = $this->get("/paygo/callback?reference={$intent->reference}");

    $response->assertRedirect(
        'https://school.test/verify/success?reference='.$intent->reference
        .'&candidate_id=STU-12345&portal_ref=APP-90210&state=signed-state-token&status=paid&payment_status=paid&result_status=ready'
    );

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($intent) {
        if ($request->url() !== 'https://school.test/hooks/easeverifier') {
            return false;
        }

        return $request->hasHeader('X-EaseVerifier-Signature')
            && $request['reference'] === $intent->reference
            && $request['candidate_id'] === 'STU-12345'
            && $request['portal_ref'] === 'APP-90210'
            && $request['result_status'] === 'ready'
            && $request['result']['candidate']['name'] === 'Hybrid Candidate';
    });

    expect($intent->fresh()->metadata['webhook_last_status'] ?? null)->toBe('delivered');
});
