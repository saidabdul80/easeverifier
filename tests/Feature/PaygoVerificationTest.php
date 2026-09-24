<?php

use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\CustomerPaystackSplitAccount;
use App\Models\CustomerPaystackSplitLedger;
use App\Models\PaygoResultAttempt;
use App\Models\PaygoVerificationIntent;
use App\Models\PaygoWallet;
use App\Models\PaystackGatewayAccount;
use App\Models\ServiceProvider;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Paygo\PaygoVerificationService;
use App\Services\ResultVerify\ResultGates\NECOResult;
use App\Services\ResultVerify\ResultGates\WAECResult;
use App\Services\ResultVerify\ResultInterface;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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

function paygoWaecParams(string $examNumber): array
{
    return [
        'txtExamNumber' => $examNumber,
        'ExamYear' => '2026',
        'ExamType' => 'MAY/JUN',
        'txtPIN' => '123456789012',
        'txtCardSerialNo' => 'WRN123456789',
    ];
}

function bindSuccessfulPaygoWaecResult(): void
{
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
            return '<html>'.$params['txtExamNumber'].'</html>';
        }

        public function parseResult(string $html): array
        {
            preg_match('/>([^<]+)</', $html, $matches);
            $examNumber = $matches[1] ?? 'UNKNOWN';

            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'Candidate '.$examNumber,
                    'exam_number' => $examNumber,
                ],
                'subjects' => [
                    ['subject' => 'MATHEMATICS', 'grade' => 'A1', 'score' => null],
                ],
                'overall' => null,
            ];
        }
    });
}

function paygoNecoParams(string $examNumber): array
{
    return [
        'exam_year' => '2026',
        'exam_type' => 'ssce_int',
        'reg_no' => $examNumber,
        'token' => '123456789012',
    ];
}

it('keeps new PayGo payment intents valid for seven days', function () {
    $this->freezeTime();

    $user = createPaygoCustomer();
    $service = createPaygoResultService();
    $paygoService = createPaygoServiceFor($user, $service);
    $paygoService->update(['reference_price' => 300]);
    $paygoService = $paygoService->fresh(['user.customer', 'verificationService']);

    $normalIntent = app(PaygoVerificationService::class)->createIntent(
        $paygoService,
        ['params' => paygoWaecParams('4140325098')],
    );
    $referenceIntent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService,
        'SEVEN-DAY-REFERENCE',
        ['params' => paygoWaecParams('4271710002')],
    );

    $expectedExpiry = now()->addDays(7)->toDateTimeString();

    expect($normalIntent->expires_at->toDateTimeString())->toBe($expectedExpiry)
        ->and($referenceIntent->expires_at->toDateTimeString())->toBe($expectedExpiry);
});

it('starts reference package validity from the confirmed payment time', function () {
    $this->freezeTime();

    $user = createPaygoCustomer();
    $service = createPaygoResultService();
    $paygoService = createPaygoServiceFor($user, $service);
    $paygoService->update(['reference_price' => 300]);
    $intent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'PAID-VALIDITY-REFERENCE',
        ['params' => paygoWaecParams('4271710002')],
    );
    $paidAt = now()->addDays(3);

    $intent = app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 300,
        'reference' => $intent->reference,
        'paid_at' => $paidAt,
        'channel' => 'card',
    ]);

    expect($intent->paid_at->toDateTimeString())->toBe($paidAt->toDateTimeString())
        ->and($intent->expires_at->toDateTimeString())->toBe($paidAt->copy()->addDays(7)->toDateTimeString());
});

it('restores an existing paid reference package within the seven day window', function () {
    $this->freezeTime();

    $user = createPaygoCustomer();
    $service = createPaygoResultService();
    $paygoService = createPaygoServiceFor($user, $service);
    $paygoService->update(['reference_price' => 300]);
    $intent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'EXISTING-PAID-REFERENCE',
        ['params' => paygoWaecParams('4250223136')],
    );
    $paidAt = now()->subDays(2);
    $intent->update([
        'status' => 'expired',
        'paid_at' => $paidAt,
        'expires_at' => $paidAt->copy()->addDay(),
    ]);

    $migration = require database_path('migrations/2026_09_24_000001_extend_paid_reference_package_validity.php');
    $migration->up();
    $intent->refresh();

    expect($intent->status)->toBe('paid')
        ->and($intent->expires_at->toDateTimeString())->toBe($paidAt->copy()->addDays(7)->toDateTimeString());
});

function bindSuccessfulPaygoNecoResult(): void
{
    app()->instance(NECOResult::class, new class implements ResultInterface
    {
        public function formFields(): array
        {
            return [
                ['name' => 'exam_year', 'label' => 'Examination Year', 'type' => 'text', 'required' => true],
                ['name' => 'exam_type', 'label' => 'Examination Type', 'type' => 'text', 'required' => true],
                ['name' => 'reg_no', 'label' => 'Examination Number', 'type' => 'text', 'required' => true],
                ['name' => 'token', 'label' => 'Result Checker Token', 'type' => 'text', 'required' => true],
            ];
        }

        public function fetchResult(array $params): string
        {
            return '<html>'.$params['reg_no'].'</html>';
        }

        public function parseResult(string $html): array
        {
            preg_match('/>([^<]+)</', $html, $matches);
            $examNumber = $matches[1] ?? 'UNKNOWN';

            return [
                'status' => 'success',
                'candidate' => [
                    'name' => 'NECO Candidate '.$examNumber,
                    'exam_number' => $examNumber,
                ],
                'subjects' => [
                    ['subject' => 'ENGLISH', 'grade' => 'A1', 'score' => null],
                ],
            ];
        }
    });
}

it('does not allow a customer to create a paygo service at or below system price', function () {
    $user = createPaygoCustomer()->fresh('customer');
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

it('repairs an unreadable paygo webhook secret when listing paygo services', function () {
    $user = createPaygoCustomer()->fresh('customer');
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    DB::table('customer_paygo_services')
        ->whereKey($paygoService->id)
        ->update(['webhook_secret' => 'not-a-valid-encrypted-value']);

    $response = $this
        ->actingAs($user)
        ->get('/customer/paygo-services');

    $response->assertOk();

    $repairedSecret = $paygoService->fresh()->ensureWebhookSecret();

    expect($repairedSecret)->toStartWith('pgw_');
});

it('completes paygo payment idempotently and credits the customer wallet once', function () {
    $user = createPaygoCustomer()->fresh('customer');
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

    $intent->refresh();

    expect((float) $user->paygoWallet()->first()->fresh()->balance)->toBe(50.0)
        ->and($intent->status)->toBe('paid')
        ->and($intent->transaction_id)->not->toBeNull()
        ->and(Transaction::count())->toBe(1);
});

it('shows paid paygo payment intents and wallet ledger entries on the paygo transactions page', function () {
    $user = createPaygoCustomer()->fresh('customer');
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

    $this->actingAs($user)
        ->get('/customer/paygo-transactions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Paygo/Transactions')
            ->has('paymentIntents.data', 1)
            ->where('paymentIntents.data.0.reference', $intent->reference)
            ->where('paymentIntents.data.0.amount', 150)
            ->where('paymentIntents.data.0.earning', 50)
            ->has('walletTransactions.data', 1)
            ->where('walletTransactions.data.0.amount', 50)
            ->where('walletTransactions.data.0.category', 'earning'));

    $this->actingAs($user)
        ->get('/customer/transactions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Transactions/Index')
            ->has('transactions.data', 1)
            ->where('transactions.data.0.reference', $intent->reference)
            ->where('transactions.data.0.type', 'credit')
            ->where('transactions.data.0.category', 'funding')
            ->where('transactions.data.0.amount', 150)
            ->where('transactions.data.0.status', 'completed'));
});

it('initializes paygo payment with configured flat paystack split', function () {
    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.test/paygo',
                'access_code' => 'ACCESS',
                'reference' => 'PGO_SPLIT',
            ],
        ]),
    ]);

    $user = createPaygoCustomer()->fresh('customer');
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 200);

    CustomerPaystackSplitAccount::create([
        'customer_id' => $user->customer->id,
        'label' => 'School account',
        'subaccount_code' => 'ACCT_school',
        'bank_name' => 'Test Bank',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_number_last4' => '6789',
        'account_name' => 'School Ltd',
        'flat_amount' => 75,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    CustomerPaystackSplitAccount::create([
        'customer_id' => $user->customer->id,
        'label' => 'Partner account',
        'subaccount_code' => 'ACCT_partner',
        'bank_name' => 'Second Bank',
        'bank_code' => '011',
        'account_number' => '1111111111',
        'account_number_last4' => '1111',
        'account_name' => 'Partner Ltd',
        'flat_amount' => 25,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this->post("/paygo/{$paygoService->public_slug}/initiate", [
        'nin' => '12345678901',
    ]);

    Http::assertSent(function ($request) {
        $payload = $request->data();

        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && data_get($payload, 'split.type') === 'flat'
            && data_get($payload, 'split.bearer_type') === 'account'
            && data_get($payload, 'split.subaccounts.0.subaccount') === 'ACCT_school'
            && data_get($payload, 'split.subaccounts.0.share') === 7500
            && data_get($payload, 'split.subaccounts.1.subaccount') === 'ACCT_partner'
            && data_get($payload, 'split.subaccounts.1.share') === 2500;
    });

    $intent = $paygoService->intents()->firstOrFail();

    expect($intent->metadata['paystack_split']['applied'])->toBeTrue()
        ->and((float) $intent->metadata['paystack_split']['total_split_amount'])->toBe(100.0)
        ->and((float) $intent->metadata['paystack_split']['main_account_remainder'])->toBe(100.0);
});

it('uses trusted customer paystack keys and settles the system share to its subaccount', function () {
    config([
        'services.paystack.public_key' => 'pk_test_system',
        'services.paystack.secret_key' => 'sk_test_system',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/transaction/verify/')) {
            return Http::response(['status' => true, 'data' => [
                'status' => 'success',
                'amount' => 20000,
                'reference' => basename(parse_url($request->url(), PHP_URL_PATH)),
                'paid_at' => now()->toISOString(),
                'channel' => 'card',
                'customer' => ['email' => 'student@example.test'],
            ]]);
        }

        return Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.test/customer-gateway',
                'access_code' => 'ACCESS_CUSTOMER',
                'reference' => $request['reference'],
            ],
        ]);
    });

    $user = createPaygoCustomer()->fresh('customer');
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 200);
    $gateway = PaystackGatewayAccount::create([
        'owner_type' => 'customer',
        'customer_id' => $user->customer->id,
        'label' => 'School Paystack',
        'environment' => 'test',
        'public_key' => 'pk_test_customer',
        'secret_key' => 'sk_test_customer',
        'key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_customer'),
        'is_trusted' => true,
        'is_active' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    CustomerPaystackSplitAccount::create([
        'customer_id' => $user->customer->id,
        'paystack_gateway_account_id' => $gateway->id,
        'beneficiary_type' => 'system',
        'label' => 'EaseVerifier settlement',
        'subaccount_code' => 'ACCT_easeverifier',
        'bank_name' => 'Test Bank',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_number_last4' => '6789',
        'account_name' => 'EaseVerifier Ltd',
        'flat_amount' => 0.01,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->post("/paygo/{$paygoService->public_slug}/initiate", [
        'nin' => '12345678901',
    ])->assertRedirect('https://checkout.test/customer-gateway');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->hasHeader('Authorization', 'Bearer sk_test_customer')
            && $request['subaccount'] === 'ACCT_easeverifier'
            && $request['transaction_charge'] === 10000
            && $request['bearer'] === 'account';
    });

    $intent = $paygoService->intents()->firstOrFail();

    expect($intent->paystack_gateway_account_id)->toBe($gateway->id)
        ->and($intent->paystack_gateway_owner_type)->toBe('customer')
        ->and($intent->settlement_strategy)->toBe('customer_gateway_system_subaccount')
        ->and(data_get($intent->metadata, 'paystack_split.subaccounts.0.share'))->toBe(10000)
        ->and(data_get($intent->metadata, 'paystack_split.subaccounts.0.beneficiary_type'))->toBe('system');

    $this->get("/paygo/callback?reference={$intent->reference}")->assertRedirect();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/transaction/verify/')
        && $request->hasHeader('Authorization', 'Bearer sk_test_customer'));

    expect($intent->fresh()->status)->toBe('paid')
        ->and(CustomerPaystackSplitLedger::where('paygo_verification_intent_id', $intent->id)->value('gateway_owner_type'))->toBe('customer')
        ->and(CustomerPaystackSplitLedger::where('paygo_verification_intent_id', $intent->id)->value('beneficiary_type'))->toBe('system');
});

it('uses system paystack keys when customer credentials are not trusted', function () {
    config([
        'services.paystack.public_key' => 'pk_test_system',
        'services.paystack.secret_key' => 'sk_test_system',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake(['https://api.paystack.co/transaction/initialize' => Http::response([
        'status' => true,
        'data' => ['authorization_url' => 'https://checkout.test/system', 'access_code' => 'SYSTEM', 'reference' => 'SYSTEM_REF'],
    ])]);

    $user = createPaygoCustomer()->fresh('customer');
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 200);

    PaystackGatewayAccount::create([
        'owner_type' => 'customer',
        'customer_id' => $user->customer->id,
        'label' => 'Untrusted gateway',
        'environment' => 'test',
        'public_key' => 'pk_test_customer',
        'secret_key' => 'sk_test_customer',
        'key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_customer'),
        'is_trusted' => false,
        'is_active' => true,
        'verification_status' => 'verified',
    ]);

    $this->post("/paygo/{$paygoService->public_slug}/initiate", ['nin' => '12345678901'])
        ->assertRedirect('https://checkout.test/system');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_test_system'));

    expect($paygoService->intents()->firstOrFail()->paystack_gateway_owner_type)->toBe('system');
});

it('does not fall back to system keys when a trusted customer gateway lacks its system subaccount', function () {
    config([
        'services.paystack.public_key' => 'pk_test_system',
        'services.paystack.secret_key' => 'sk_test_system',
    ]);
    Http::fake();

    $user = createPaygoCustomer()->fresh('customer');
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 200);

    PaystackGatewayAccount::create([
        'owner_type' => 'customer',
        'customer_id' => $user->customer->id,
        'label' => 'Customer gateway',
        'environment' => 'test',
        'public_key' => 'pk_test_customer',
        'secret_key' => 'sk_test_customer',
        'key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_customer'),
        'is_trusted' => true,
        'is_active' => true,
        'verification_status' => 'verified',
    ]);

    $this->from("/paygo/{$paygoService->public_slug}/initiate")
        ->post("/paygo/{$paygoService->public_slug}/initiate", ['nin' => '12345678901'])
        ->assertRedirect("/paygo/{$paygoService->public_slug}/initiate")
        ->assertSessionHas('error', 'This customer Paystack account is missing an active EaseVerifier settlement subaccount.');

    Http::assertNothingSent();
    expect($paygoService->intents()->firstOrFail()->paystack_gateway_owner_type)->toBe('customer');
});

it('does not credit paygo wallet when paystack split was applied', function () {
    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    $wallet = PaygoWallet::create([
        'user_id' => $user->id,
        'balance' => 10,
        'pending_withdrawal' => 0,
        'currency' => 'NGN',
        'is_active' => true,
    ]);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'nin' => '12345678901',
    ]);

    $intent->update([
        'metadata' => array_merge($intent->metadata ?? [], [
            'paystack_split' => [
                'applied' => true,
                'type' => 'flat',
                'bearer_type' => 'account',
                'reference' => 'SPLIT-'.$intent->reference,
                'main_account_remainder' => 100,
                'wallet_credit_skipped' => true,
                'subaccounts' => [
                    [
                        'label' => 'School account',
                        'subaccount_code' => 'ACCT_school',
                        'share' => 5000,
                        'flat_amount' => 50,
                    ],
                ],
            ],
        ]),
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $intent->refresh();

    expect((float) $wallet->fresh()->balance)->toBe(10.0)
        ->and($wallet->transactions()->count())->toBe(0)
        ->and($intent->transaction_id)->not->toBeNull()
        ->and(Transaction::count())->toBe(1)
        ->and(CustomerPaystackSplitLedger::count())->toBe(1)
        ->and((float) CustomerPaystackSplitLedger::first()->flat_amount)->toBe(50.0)
        ->and($intent->metadata['paygo_wallet_credit_skipped'])->toBeTrue()
        ->and((float) $intent->metadata['paygo_earning'])->toBe(0.0);
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

it('identifies reference packages in customer PayGo transactions', function () {
    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_system_price' => 200]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 300);
    $paygoService->update(['reference_price' => 1000]);

    app(PaygoVerificationService::class)->createIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        ['params' => paygoWaecParams('4140325098')],
    );
    app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'QAP-REFERENCE-PACKAGE',
        ['params' => paygoWaecParams('4271710002')],
    );

    $this->actingAs($user)
        ->getJson("/customer/paygo-services/{$paygoService->id}/transactions")
        ->assertOk()
        ->assertJsonPath('payment_intents.0.reference', 'QAP-REFERENCE-PACKAGE')
        ->assertJsonPath('payment_intents.0.flow_type', 'result_reference')
        ->assertJsonPath('payment_intents.0.package_type', 'reference')
        ->assertJsonPath('payment_intents.0.amount', 1000)
        ->assertJsonPath('payment_intents.0.system_price', 200)
        ->assertJsonPath('payment_intents.0.earning', 800)
        ->assertJsonPath('payment_intents.0.max_fetches', 2)
        ->assertJsonPath('payment_intents.1.flow_type', 'result')
        ->assertJsonPath('payment_intents.1.package_type', 'normal')
        ->assertJsonPath('payment_intents.1.amount', 300)
        ->assertJsonPath('payment_intents.1.system_price', 100)
        ->assertJsonPath('payment_intents.1.earning', 200);
});

it('shows realistic PayGo analytics on the customer transactions page', function () {
    $this->withoutVite();

    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_system_price' => 200]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 300);
    $paygoService->update(['reference_price' => 1000]);

    $normalIntent = app(PaygoVerificationService::class)->createIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        ['params' => paygoWaecParams('4140325098')],
    );
    $normalIntent->update(['status' => 'paid', 'paid_at' => now()]);

    $referenceIntent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'QAP-ANALYTICS-PAID',
        ['params' => paygoWaecParams('4271710002')],
    );
    $referenceIntent->update(['status' => 'paid', 'paid_at' => now()]);

    app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'QAP-ANALYTICS-PENDING',
        ['params' => paygoWaecParams('4253217003')],
    );

    $this->actingAs($user)
        ->get('/customer/transactions?tab=paygo')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Transactions/Index')
            ->where('activeTab', 'paygo')
            ->has('paygoIntents.data', 3)
            ->where('paygoStats.gross_revenue', 1300)
            ->where('paygoStats.system_settlement', 300)
            ->where('paygoStats.net_earnings', 1000)
            ->where('paygoStats.successful_payments', 2)
            ->where('paygoStats.reference_packages', 1)
            ->where('paygoStats.this_month_revenue', 1300)
            ->where('paygoStats.this_month_earnings', 1000));

    $filteredUrl = '/customer/transactions?'.http_build_query([
        'tab' => 'paygo',
        'paygo_status' => 'paid',
        'paygo_package' => 'reference',
        'paygo_date_from' => today()->toDateString(),
        'paygo_date_to' => today()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get($filteredUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('paygoIntents.data', 1)
            ->where('paygoIntents.data.0.reference', 'QAP-ANALYTICS-PAID')
            ->where('paygoStats.gross_revenue', 1000)
            ->where('paygoStats.system_settlement', 200)
            ->where('paygoStats.net_earnings', 800)
            ->where('paygoStats.successful_payments', 1)
            ->where('paygoStats.reference_packages', 1));

    $exportResponse = $this->actingAs($user)->get('/customer/transactions/export?'.http_build_query([
        'tab' => 'paygo',
        'paygo_status' => 'paid',
        'paygo_package' => 'reference',
        'paygo_date_from' => today()->toDateString(),
        'paygo_date_to' => today()->toDateString(),
    ]));

    $exportResponse->assertOk();
    expect($exportResponse->streamedContent())
        ->toContain('QAP-ANALYTICS-PAID')
        ->not->toContain('QAP-ANALYTICS-PENDING');
});

it('reuses a paid paygo result intent instead of initializing another payment while pulls remain', function () {
    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake();

    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_fetch_limit' => 2]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);
    $paygoService->update([
        'success_url' => 'https://school.test/result_verify_callback.php',
        'failure_url' => 'https://school.test/verify/failure',
        'callback_mode' => 'redirect',
    ]);
    $params = [
        'txtExamNumber' => '1234567890',
        'ExamYear' => '2025',
        'ExamType' => 'MAY/JUN',
        'txtPIN' => '123456789012',
        'txtCardSerialNo' => 'WRN123456789',
    ];

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService->fresh(['user.customer', 'verificationService']), [
        'params' => $params,
        'email' => 'student@example.com',
        'phone' => '08012345678',
    ]);

    $verification = VerificationRequest::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'reference' => VerificationRequest::generateReference(),
        'search_parameter' => '1234567890',
        'request_data' => ['board' => 'waec', 'action' => 'fetch'],
        'response_data' => [
            'candidate' => [
                'name' => 'Paid Candidate',
                'exam_number' => '1234567890',
            ],
        ],
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'paygo',
        'completed_at' => now(),
    ]);

    $intent->update([
        'status' => 'paid',
        'paid_at' => now(),
        'reference_fetches' => 1,
        'verification_request_id' => $verification->id,
        'metadata' => array_merge($intent->metadata ?? [], [
            'success_url_snapshot' => 'https://school.test',
        ]),
    ]);

    $this->post("/paygo/results/{$paygoService->public_slug}", array_merge($params, [
        'email' => 'student@example.com',
        'phone' => '08012345678',
        'portal_ref' => 'QAP-20260913214250-AA5FF6',
        'state' => '5f32a8f365293b3e6499c99a69d76f2f',
    ]))->assertRedirect(
        'https://school.test/result_verify_callback.php?reference='.$intent->reference
        .'&portal_ref=QAP-20260913214250-AA5FF6&state=5f32a8f365293b3e6499c99a69d76f2f&status=paid&payment_status=paid&result_status=ready'
    );

    $this
        ->withHeaders(['X-Inertia' => 'true'])
        ->post("/paygo/results/{$paygoService->public_slug}", array_merge($params, [
            'email' => 'student@example.com',
            'phone' => '08012345678',
            'portal_ref' => 'QAP-20260913214250-AA5FF6',
            'state' => '5f32a8f365293b3e6499c99a69d76f2f',
        ]))
        ->assertStatus(409)
        ->assertHeader(
            'X-Inertia-Location',
            'https://school.test/result_verify_callback.php?reference='.$intent->reference
            .'&portal_ref=QAP-20260913214250-AA5FF6&state=5f32a8f365293b3e6499c99a69d76f2f&status=paid&payment_status=paid&result_status=ready',
        );

    expect(PaygoVerificationIntent::count())->toBe(1);
    Http::assertNothingSent();

    expect(app(PaygoVerificationService::class)->findPaidReusableResultIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        array_merge($params, ['txtPIN' => '000000000000']),
    ))->toBeNull();
});

it('initializes a result reference package with the school supplied reference and package price', function () {
    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        '*/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.test/portal-ref-1',
                'access_code' => 'access-code',
                'reference' => 'PORTAL-REF-1',
            ],
        ], 200),
        '*/transaction/verify/PORTAL-REF-1' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'pending',
                'amount' => 45000,
                'reference' => 'PORTAL-REF-1',
            ],
        ]),
    ]);

    $user = createPaygoCustomer();
    $user->customer->update([
        'paygo_result_reference_system_price' => 300,
    ]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);
    $paygoService->update([
        'reference_price' => 450,
        'success_url' => 'https://school.test/result_verify_callback.php',
        'failure_url' => 'https://school.test/result_verify_failed.php',
    ]);

    $this
        ->withHeaders(['X-Inertia' => 'true'])
        ->post("/paygo/results/{$paygoService->public_slug}", array_merge(paygoWaecParams('4310516058'), [
            'email' => 'student@example.com',
            'phone' => '08012345678',
            'portal_ref' => 'APP-123',
            'state' => 'signed-state',
            'reference' => 'PORTAL-REF-1',
        ]))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.test/portal-ref-1');

    $intent = PaygoVerificationIntent::where('reference', 'PORTAL-REF-1')->firstOrFail();

    expect($intent->flow_type)->toBe('result_reference')
        ->and((float) $intent->amount)->toBe(450.0)
        ->and((float) $intent->system_price_snapshot)->toBe(300.0)
        ->and($intent->max_fetches_snapshot)->toBe(2)
        ->and($intent->metadata['portal_state'])->toBe('signed-state')
        ->and($intent->metadata['paystack_checkout']['authorization_url'])->toBe('https://checkout.paystack.test/portal-ref-1');

    $paygoService->update(['reference_price' => 1000]);

    $this
        ->withHeaders(['X-Inertia' => 'true'])
        ->post("/paygo/results/{$paygoService->public_slug}", array_merge(paygoWaecParams('4310516058'), [
            'email' => 'student@example.com',
            'phone' => '08012345678',
            'portal_ref' => 'APP-123',
            'state' => 'signed-state',
            'reference' => 'PORTAL-REF-1',
        ]))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.test/portal-ref-1');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request['reference'] === 'PORTAL-REF-1'
            && $request['amount'] === 45000;
    });
    Http::assertSent(fn (\Illuminate\Http\Client\Request $request) => $request->url() === 'https://api.paystack.co/transaction/verify/PORTAL-REF-1');
    Http::assertSentCount(2);

    expect($intent->fresh()->amount)->toBe('450.00')
        ->and($intent->fresh()->system_price_snapshot)->toBe('300.00');
});

it('uses the admin reference package settlement for the EaseVerifier customer gateway split', function () {
    config([
        'services.paystack.public_key' => 'pk_test_system',
        'services.paystack.secret_key' => 'sk_test_system',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        '*/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.test/reference-package',
                'access_code' => 'ACCESS_REFERENCE_PACKAGE',
                'reference' => 'APP-REFERENCE-SPLIT',
            ],
        ]),
    ]);
    Log::spy();

    $user = createPaygoCustomer()->fresh('customer');
    $user->customer->update(['paygo_result_reference_system_price' => 1000]);
    $service = createPaygoResultService(price: 200);
    $paygoService = createPaygoServiceFor($user, $service, price: 300);
    $paygoService->update(['reference_price' => 1400]);
    $gateway = PaystackGatewayAccount::create([
        'owner_type' => 'customer',
        'customer_id' => $user->customer->id,
        'label' => 'School Paystack',
        'environment' => 'test',
        'public_key' => 'pk_test_customer',
        'secret_key' => 'sk_test_customer',
        'key_fingerprint' => PaystackGatewayAccount::fingerprint('sk_test_customer'),
        'is_trusted' => true,
        'is_active' => true,
        'verification_status' => 'verified',
        'verified_at' => now(),
    ]);

    CustomerPaystackSplitAccount::create([
        'customer_id' => $user->customer->id,
        'paystack_gateway_account_id' => $gateway->id,
        'beneficiary_type' => 'system',
        'label' => 'EaseVerifier settlement',
        'subaccount_code' => 'ACCT_easeverifier',
        'bank_name' => 'Test Bank',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_number_last4' => '6789',
        'account_name' => 'EaseVerifier Ltd',
        'flat_amount' => 0.01,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post("/paygo/results/{$paygoService->public_slug}", array_merge(paygoWaecParams('4310516058'), [
            'email' => 'student@example.com',
            'phone' => '08012345678',
            'reference' => 'APP-REFERENCE-SPLIT',
        ]))
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.test/reference-package');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
        return $request->url() === 'https://api.paystack.co/transaction/initialize'
            && $request->hasHeader('Authorization', 'Bearer sk_test_customer')
            && $request['amount'] === 140000
            && $request['subaccount'] === 'ACCT_easeverifier'
            && $request['transaction_charge'] === 40000
            && $request['bearer'] === 'account';
    });

    Log::shouldHaveReceived('info')
        ->with('EaseVerifier PayGo Paystack split prepared', \Mockery::on(fn (array $context) => $context['payment_reference'] === 'APP-REFERENCE-SPLIT'
            && $context['is_reference_package'] === true
            && $context['transaction_amount_kobo'] === 1400
            && $context['system_share_kobo'] === 1000
            && $context['customer_remainder_kobo'] === 400
            && $context['transaction_charge_kobo'] === 40000
            && $context['subaccount_code'] === 'ACCT_easeverifier'
        ));

    $intent = PaygoVerificationIntent::where('reference', 'APP-REFERENCE-SPLIT')->firstOrFail();

    expect((float) $intent->amount)->toBe(1400.0)
        ->and((float) $intent->system_price_snapshot)->toBe(1000.0)
        ->and(data_get($intent->metadata, 'paystack_split.total_split_amount_kobo'))->toBe(100000)
        ->and(data_get($intent->metadata, 'paystack_split.main_account_remainder_kobo'))->toBe(40000);
});

it('keeps failed result reference payments on the local result form', function () {
    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    Http::fake([
        '*/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'failed',
                'amount' => 100000,
                'reference' => 'APP-90210',
            ],
        ]),
    ]);

    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_system_price' => 300]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 500);
    $paygoService->update([
        'reference_price' => 1000,
        'failure_url' => 'http://quickapple.test/std/result_verify_callback.php',
    ]);

    app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        'APP-90210',
        [
            'params' => paygoWaecParams('4271710002'),
            'email' => 'said@gmail.com',
            'phone' => '080937282',
            'portal_context' => [
                'candidate_id' => 'STU-12345',
                'state' => 'signed-state',
                'sitting' => 1,
            ],
        ],
    );

    $response = $this->get('/paygo/callback?reference=APP-90210');

    $response
        ->assertRedirect(route('paygo.results.service', [
            'publicSlug' => $paygoService->public_slug,
            'reference' => 'APP-90210',
            'candidate_id' => 'STU-12345',
            'sitting' => 1,
            'state' => 'signed-state',
            'email' => 'said@gmail.com',
            'phone' => '080937282',
        ]))
        ->assertSessionHas('error', 'Payment was not completed.');

    expect(PaygoVerificationIntent::where('reference', 'APP-90210')->value('status'))->toBe('failed')
        ->and($response->headers->get('Location'))->not->toContain('quickapple.test');
});

it('reconciles a paid reference package before returning to its cached checkout', function () {
    $this->withoutVite();

    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    bindSuccessfulPaygoWaecResult();

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/transaction/verify/APP-90210')) {
            return Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 100000,
                    'reference' => 'APP-90210',
                    'paid_at' => now()->toISOString(),
                    'channel' => 'card',
                    'customer' => ['email' => 'said@gmail.com'],
                ],
            ]);
        }

        return Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.test/app-90210',
                'access_code' => 'APP-90210-CODE',
                'reference' => 'APP-90210',
            ],
        ]);
    });

    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_system_price' => 300]);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 500);
    $paygoService->update([
        'reference_price' => 1000,
        'success_url' => 'http://quickapple.test/std/result_verify_callback.php',
    ]);
    $payload = array_merge(paygoWaecParams('4271710002'), [
        'email' => 'said@gmail.com',
        'phone' => '080937282',
        'candidate_id' => 'STU-12345',
        'state' => 'signed-state',
        'sitting' => 1,
        'reference' => 'APP-90210',
    ]);

    $this->withHeaders(['X-Inertia' => 'true'])
        ->post("/paygo/results/{$paygoService->public_slug}", $payload)
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.test/app-90210');

    $this->post("/paygo/results/{$paygoService->public_slug}", $payload)
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'http://quickapple.test/std/result_verify_callback.php?reference=APP-90210&candidate_id=STU-12345&sitting=1&state=signed-state&status=paid&payment_status=paid&result_status=ready&attempts_remaining=1');

    $intent = PaygoVerificationIntent::where('reference', 'APP-90210')->firstOrFail();

    expect($intent->status)->toBe('paid')
        ->and($intent->paid_at)->not->toBeNull();

    Http::assertSentCount(2);
});

it('allows two successful result fetches under one paid portal reference and blocks the third', function () {
    bindSuccessfulPaygoWaecResult();

    $user = createPaygoCustomer();
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);
    $paygoService->update(['reference_price' => 350]);

    $intent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent($paygoService->fresh(['user.customer', 'verificationService']), 'SCHOOL-REF-2', [
        'params' => paygoWaecParams('4310516058'),
        'email' => 'student@example.com',
        'phone' => '08012345678',
        'portal_context' => [
            'state' => 'state-token',
        ],
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 350,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $first = app(PaygoVerificationService::class)->fetchResultForPaidIntent($intent->fresh(), '127.0.0.1', paygoWaecParams('4310516058'));
    $second = app(PaygoVerificationService::class)->fetchResultForPaidIntent($intent->fresh(), '127.0.0.1', paygoWaecParams('4310516059'));

    expect($first['success'])->toBeTrue()
        ->and($first['attempts_remaining'])->toBe(1)
        ->and($second['success'])->toBeTrue()
        ->and($second['attempts_remaining'])->toBe(0)
        ->and($intent->fresh()->verification_attempts)->toBe(2)
        ->and(PaygoResultAttempt::where('paygo_verification_intent_id', $intent->id)->where('success_counted', true)->count())->toBe(2);

    expect(fn () => app(PaygoVerificationService::class)->fetchResultForPaidIntent(
        $intent->fresh(),
        '127.0.0.1',
        paygoWaecParams('4310516060'),
    ))->toThrow(RuntimeException::class, 'successful fetch limit');
});

it('allows a paid portal reference package to fetch a second result from a different board', function () {
    $this->withoutVite();

    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

    bindSuccessfulPaygoWaecResult();
    bindSuccessfulPaygoNecoResult();

    $user = createPaygoCustomer();
    $waecService = createPaygoResultService(price: 100);
    $necoService = createPaygoResultService(slug: 'neco-result-fetch', price: 100);
    $waecPaygoService = createPaygoServiceFor($user, $waecService, price: 150);
    $necoPaygoService = createPaygoServiceFor($user, $necoService, price: 150);
    $waecPaygoService->update(['reference_price' => 350]);
    $necoPaygoService->update(['reference_price' => 350]);

    $intent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent($waecPaygoService->fresh(['user.customer', 'verificationService']), 'SCHOOL-CROSS-REF', [
        'params' => paygoWaecParams('4310516058'),
        'email' => 'student@example.com',
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 350,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $first = app(PaygoVerificationService::class)->fetchResultForPaidIntent(
        $intent->fresh(),
        '127.0.0.1',
        paygoWaecParams('4310516058'),
        $waecPaygoService->fresh(['user.customer', 'verificationService']),
    );

    $sameReference = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent($necoPaygoService->fresh(['user.customer', 'verificationService']), 'SCHOOL-CROSS-REF', [
        'params' => paygoNecoParams('NECO123456'),
        'email' => 'student@example.com',
    ]);

    $second = app(PaygoVerificationService::class)->fetchResultForPaidIntent(
        $sameReference->fresh(),
        '127.0.0.1',
        paygoNecoParams('NECO123456'),
        $necoPaygoService->fresh(['user.customer', 'verificationService']),
    );

    expect($sameReference->id)->toBe($intent->id)
        ->and($first['success'])->toBeTrue()
        ->and($second['success'])->toBeTrue()
        ->and($second['data']['candidate']['name'])->toBe('NECO Candidate NECO123456')
        ->and($intent->fresh()->verification_attempts)->toBe(2)
        ->and(PaygoResultAttempt::where('paygo_verification_intent_id', $intent->id)->pluck('verification_service_id')->all())
        ->toContain($waecService->id, $necoService->id);

    $intent->fresh()->update([
        'verification_request_id' => $first['verification']->id,
        'lookup_label' => 'WAEC 4310516058',
    ]);

    $this->get('/paygo/results/paid/SCHOOL-CROSS-REF')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Paygo/ResultPaid')
            ->where('paygoService.board', 'NECO')
            ->where('intent.lookup_label', 'NECO NECO123456')
            ->where('result.data.candidate.name', 'NECO Candidate NECO123456')
            ->where('intent.fetches_used', 2)
            ->where('intent.fetches_remaining', 0)
        );

    $this->getJson('/api/paygo/results/SCHOOL-CROSS-REF')
        ->assertOk()
        ->assertJsonPath('lookup_label', 'NECO NECO123456')
        ->assertJsonPath('data.candidate.name', 'NECO Candidate NECO123456')
        ->assertJsonPath('fetches_remaining', 0);
});

it('lets admins manage PayGo users collected from verification intents', function () {
    $this->withoutVite();

    Role::findOrCreate('admin');
    $admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('admin');

    $user = createPaygoCustomer();
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    app(PaygoVerificationService::class)->createIntent($paygoService, [
        'params' => [
            'txtExamNumber' => '1234567890',
            'ExamYear' => '2025',
            'ExamType' => 'MAY/JUN',
            'txtPIN' => '123456789012',
            'txtCardSerialNo' => 'WRN123456789',
        ],
        'email' => 'student@example.com',
        'phone' => '08012345678',
    ]);

    PaygoVerificationIntent::query()->firstOrFail()->update(['status' => 'paid', 'paid_at' => now()]);

    $this
        ->actingAs($admin)
        ->get('/admin/paygo-users?search=student@example.com')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/PaygoUsers/Index')
            ->where('paygoUsers.data.0.email', 'student@example.com')
            ->where('paygoUsers.data.0.phone', '08012345678')
            ->where('paygoUsers.data.0.flow_type', 'result')
            ->where('paygoUsers.data.0.status', 'paid')
            ->where('stats.total', 1)
            ->where('stats.paid', 1)
        );
});

it('stores PayGo identity contact email and phone for admin management', function () {
    $user = createPaygoCustomer();
    $service = createPaygoNinService(100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'nin' => '12345678901',
        'email' => 'identity@example.com',
        'phone' => '08098765432',
    ]);

    expect($intent->buyer_phone)->toBe('08098765432')
        ->and($intent->metadata['buyer_email'])->toBe('identity@example.com');
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
        ->and($intent->fresh()->verificationRequest->response_data['candidate']['name'])->toBe('Paid Candidate')
        ->and($intent->fresh()->verificationRequest->source)->toBe('paygo');

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

it('allows a school to pull a paid result when a matching completed verification exists later', function () {
    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_fetch_limit' => 2]);
    $service = createPaygoResultService(slug: 'nabteb-result-fetch', price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);

    $intent = app(PaygoVerificationService::class)->createIntent($paygoService, [
        'params' => [
            'candid' => '27054614',
            'examtype' => '01',
            'examyear' => '2011',
            'serial' => '123456789012',
            'pin' => '999988887777',
        ],
    ]);

    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 150,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    VerificationRequest::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'reference' => VerificationRequest::generateReference(),
        'search_parameter' => '27054614',
        'request_data' => ['board' => 'nabteb', 'action' => 'fetch'],
        'response_data' => [
            'candidate' => [
                'name' => 'Recovered Candidate',
                'exam_number' => '27054614',
            ],
            'subjects' => [
                ['subject' => 'MATHEMATICS', 'grade' => 'A1'],
            ],
        ],
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'web',
        'completed_at' => now(),
    ]);

    $this->getJson("/api/paygo/results/{$intent->reference}")
        ->assertOk()
        ->assertJsonPath('data.candidate.name', 'Recovered Candidate')
        ->assertJsonPath('fetches_remaining', 1);

    expect($intent->fresh()->verificationRequest?->response_data['candidate']['name'])->toBe('Recovered Candidate')
        ->and($intent->fresh()->metadata['verification_status'] ?? null)->toBe('completed');
});

it('redirects back to the school portal and posts a webhook for hybrid paygo result callbacks', function () {
    config([
        'services.paystack.public_key' => 'paystack-public',
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
    ]);

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

it('does not turn a successful result into a failure when webhook delivery fails', function () {
    Http::fake([
        'https://school.test/hooks/missing' => Http::response('Not Found', 404),
    ]);

    $user = createPaygoCustomer();
    $user->customer->update(['webhook_url' => 'https://school.test/hooks/missing']);
    $service = createPaygoResultService(price: 100);
    $paygoService = createPaygoServiceFor($user, $service, price: 150);
    $paygoService->update(['callback_mode' => 'webhook']);

    $intent = app(PaygoVerificationService::class)->createIntent(
        $paygoService->fresh(['user.customer', 'verificationService']),
        ['params' => paygoWaecParams('4271710002')],
    );
    $intent->update([
        'status' => 'paid',
        'paid_at' => now(),
        'metadata' => array_merge($intent->metadata ?? [], [
            'callback_mode' => 'webhook',
            'verification_status' => 'completed',
        ]),
    ]);

    app(\App\Services\Paygo\PaygoResultCallbackService::class)->sendResultWebhook(
        $intent->fresh(['paygoService.user.customer']),
        true,
        ['candidate' => ['name' => 'Successful Candidate']],
    );

    $intent->refresh();

    expect($intent->status)->toBe('paid')
        ->and($intent->metadata['verification_status'])->toBe('completed')
        ->and($intent->metadata['webhook_last_status'])->toBe('failed')
        ->and($intent->metadata['webhook_last_error'])->toContain('404');
});

it('keeps provider result errors local instead of using the success redirect', function () {
    $this->withoutVite();

    $error = 'RESULT CHECKER CARD HAS BEEN USED BY ANOTHER CANDIDATE RESULT CHECKER CARD HAS BEEN USED BY ANOTHER CANDIDATE';
    $user = createPaygoCustomer();
    $user->customer->update(['paygo_result_reference_system_price' => 200]);
    $originalService = createPaygoResultService('neco-everify-result-fetch', 100);
    $originalPaygoService = createPaygoServiceFor($user, $originalService, price: 200);
    $originalPaygoService->update(['reference_price' => 400]);
    $waecService = createPaygoResultService(price: 100);
    $waecPaygoService = createPaygoServiceFor($user, $waecService, price: 200);
    $waecPaygoService->update([
        'reference_price' => 400,
        'success_url' => 'http://quickapple.test/std/result_verify_callback.php',
    ]);

    app()->instance(WAECResult::class, new class($error) implements ResultInterface
    {
        public function __construct(private readonly string $error) {}

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
            return '<html>card used</html>';
        }

        public function parseResult(string $html): array
        {
            return [
                'status' => 'error',
                'code' => 'UNKNOWN_ERROR',
                'message' => $this->error,
            ];
        }
    });

    $intent = app(PaygoVerificationService::class)->createOrFindResultReferenceIntent(
        $originalPaygoService->fresh(['user.customer', 'verificationService']),
        'APP-CARD-USED',
        ['params' => ['examno' => 'NECO-ORIGINAL-LOOKUP']],
    );
    app(PaygoVerificationService::class)->completePayment($intent->reference, [
        'amount' => 400,
        'reference' => $intent->reference,
        'paid_at' => now(),
        'channel' => 'card',
    ]);

    $formUrl = "/paygo/results/{$waecPaygoService->public_slug}?reference=APP-CARD-USED";
    $this->from($formUrl)
        ->post("/paygo/results/{$waecPaygoService->public_slug}", array_merge(paygoWaecParams('4271710002'), [
            'reference' => 'APP-CARD-USED',
        ]))
        ->assertRedirect($formUrl)
        ->assertSessionHas('error', $error);

    expect($intent->fresh()->status)->toBe('paid')
        ->and($intent->fresh()->verification_attempts)->toBe(0)
        ->and($intent->fresh()->metadata['latest_customer_paygo_service_id'])->toBe($waecPaygoService->id)
        ->and($intent->fresh()->customer_paygo_service_id)->toBe($originalPaygoService->id);
});
