<?php

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\CustomerResultPinPricing;
use App\Models\ResultPinOrder;
use App\Models\ResultPinProduct;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

function createResultPinProduct(float $price = 1000): ResultPinProduct
{
    return ResultPinProduct::create([
        'name' => 'WAEC Scratch Card',
        'slug' => 'waec-scratch-card-test-'.uniqid(),
        'board' => 'waec',
        'provider' => 'naijaresultpins',
        'provider_card_type_id' => '1',
        'price' => $price,
        'cost_price' => 800,
        'min_quantity' => 1,
        'max_quantity' => 100,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function createPinCustomer(float $balance = 5000): User
{
    Role::firstOrCreate(['name' => 'customer']);

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

    $user->wallet->update([
        'balance' => $balance,
        'bonus_balance' => 0,
    ]);

    return $user->fresh('wallet');
}

function fakeSuccessfulPinProvider(): void
{
    Http::fake([
        'https://www.naijaresultpins.com/api/v1/*' => Http::response([
            'status' => true,
            'code' => '000',
            'message' => '2 WAEC Scratch Card generated',
            'reference' => 'PROVIDER-REF',
            'quantity' => '2',
            'amount' => '1600.00',
            'cards' => [
                ['pin' => '111122223333', 'serial_no' => 'WRN000000001'],
                ['pin' => '444455556666', 'serial_no' => 'WRN000000002'],
            ],
        ]),
    ]);
}

it('allows API customers to purchase result pins with wallet balance', function () {
    config(['services.naija_result_pins.token' => 'provider-token']);
    $product = createResultPinProduct(1000);
    $user = createPinCustomer(5000);
    $apiKey = ApiKey::generate($user->id, 'Live', 'live');
    fakeSuccessfulPinProvider();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/result-pins/purchase', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.pins.0.pin', '111122223333');

    expect($user->wallet->fresh()->balance)->toBe('3000.00')
        ->and(ResultPinOrder::where('user_id', $user->id)->where('status', 'completed')->count())->toBe(1);
});

it('loads result pin products from the provider through the backend', function () {
    config(['services.naija_result_pins.token' => 'provider-token']);
    $user = createPinCustomer(5000);
    $apiKey = ApiKey::generate($user->id, 'Live', 'live');

    Http::fake([
        'https://www.naijaresultpins.com/api/v1' => Http::response([
            'status' => true,
            'data' => [
                [
                    'card_type_id' => '1',
                    'name' => 'WAEC Scratch Card',
                    'price' => '3500',
                ],
                [
                    'card_type_id' => '2',
                    'name' => 'NECO Result Token',
                    'unit_amount' => '1200',
                ],
            ],
        ]),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->getJson('/api/v1/result-pins/products');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'card_type_id' => '2',
            'board' => 'neco',
        ]);

    expect(ResultPinProduct::where('provider_card_type_id', '2')->exists())->toBeTrue();
    expect(ResultPinProduct::where('provider_card_type_id', '2')->value('price'))->toBe('1200.00');
});

it('does not overwrite admin configured result pin selling price during provider sync', function () {
    config(['services.naija_result_pins.token' => 'provider-token']);
    createResultPinProduct(1500);

    Http::fake([
        'https://www.naijaresultpins.com/api/v1' => Http::response([
            'status' => true,
            'data' => [
                [
                    'card_type_id' => '1',
                    'card_name' => 'WAEC Scratch Card',
                    'unit_amount' => '5140',
                    'availability' => 'In Stock',
                ],
            ],
        ]),
    ]);

    app(\App\Services\ResultPins\ResultPinPurchaseService::class)->syncProviderProducts();

    $product = ResultPinProduct::where('provider_card_type_id', '1')->firstOrFail();

    expect($product->price)->toBe('1500.00')
        ->and($product->cost_price)->toBe('5140.00');
});

it('uses customer specific result pin pricing for API purchases', function () {
    config(['services.naija_result_pins.token' => 'provider-token']);
    $product = createResultPinProduct(1000);
    $user = createPinCustomer(5000);
    $apiKey = ApiKey::generate($user->id, 'Live', 'live');

    CustomerResultPinPricing::create([
        'user_id' => $user->id,
        'result_pin_product_id' => $product->id,
        'price' => 750,
        'is_active' => true,
    ]);

    fakeSuccessfulPinProvider();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/result-pins/purchase', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response->assertOk();

    $order = ResultPinOrder::where('user_id', $user->id)->firstOrFail();

    expect($order->unit_price)->toBe('750.00')
        ->and($order->total_amount)->toBe('1500.00')
        ->and($user->wallet->fresh()->balance)->toBe('3500.00');
});

it('rejects API result pin purchase when wallet balance is insufficient', function () {
    $product = createResultPinProduct(1000);
    $user = createPinCustomer(500);
    $apiKey = ApiKey::generate($user->id, 'Live', 'live');
    Http::fake();

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/result-pins/purchase', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response
        ->assertStatus(402)
        ->assertJsonPath('error_code', 'INSUFFICIENT_FUNDS');

    expect($user->wallet->fresh()->balance)->toBe('500.00')
        ->and(ResultPinOrder::count())->toBe(0);
});

it('refunds wallet debit when provider result pin purchase fails', function () {
    $product = createResultPinProduct(1000);
    $user = createPinCustomer(5000);
    $apiKey = ApiKey::generate($user->id, 'Live', 'live');
    Http::fake([
        'https://www.naijaresultpins.com/api/v1' => Http::response([
            'status' => true,
            'data' => [
                [
                    'card_type_id' => '1',
                    'card_name' => 'WAEC Scratch Card',
                    'unit_amount' => '1000',
                    'availability' => 'In Stock',
                ],
            ],
        ]),
        'https://www.naijaresultpins.com/api/v1/exam-card/buy' => Http::response([
            'status' => false,
            'code' => '011',
            'message' => 'Quantity ordered exceeds cards available.',
        ]),
    ]);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$apiKey->getBearerToken(),
    ])->postJson('/api/v1/result-pins/purchase', [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $response
        ->assertStatus(400)
        ->assertJsonPath('error_code', 'PIN_PURCHASE_FAILED');

    expect($user->wallet->fresh()->balance)->toBe('5000.00')
        ->and(ResultPinOrder::first()->status)->toBe('failed');
});

it('creates a guest public pin order, verifies payment, purchases pins and shows the order', function () {
    config([
        'services.paystack.secret_key' => 'paystack-secret',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.naija_result_pins.token' => 'provider-token',
    ]);
    Role::firstOrCreate(['name' => 'customer']);
    $product = createResultPinProduct(1000);

    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.test/pay',
                'access_code' => 'ACCESS',
                'reference' => 'PAY_PUBLIC',
            ],
        ]),
        'https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 200000,
                'reference' => 'PAY_PUBLIC',
                'paid_at' => now()->toISOString(),
                'channel' => 'card',
                'customer' => ['email' => 'buyer@example.com'],
            ],
        ]),
        'https://www.naijaresultpins.com/api/v1' => Http::response([
            'status' => true,
            'data' => [
                [
                    'card_type_id' => '1',
                    'card_name' => 'WAEC Scratch Card',
                    'unit_amount' => '1000',
                    'availability' => 'In Stock',
                ],
            ],
        ]),
        'https://www.naijaresultpins.com/api/v1/exam-card/buy' => Http::response([
            'status' => true,
            'code' => '000',
            'message' => '2 WAEC Scratch Card generated',
            'reference' => 'PROVIDER-PUBLIC',
            'quantity' => '2',
            'amount' => '1600.00',
            'cards' => [
                ['pin' => '123456789012', 'serial_no' => 'WRN000000011'],
                ['pin' => '210987654321', 'serial_no' => 'WRN000000012'],
            ],
        ]),
    ]);

    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('/result-pins/purchase', [
        'email' => 'buyer@example.com',
        'phone' => '08000000000',
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertRedirect('https://checkout.test/pay');

    expect(User::where('email', 'buyer@example.com')->exists())->toBeFalse();

    $order = ResultPinOrder::where('buyer_email', 'buyer@example.com')->firstOrFail();

    $this->get('/result-pins/callback?reference='.$order->reference)
        ->assertRedirect(route('public.result-pins.show', ['order' => $order->reference]));

    $this->assertGuest();

    $order->refresh();
    expect($order->status)->toBe('completed')
        ->and($order->pins[0]['pin'])->toBe('123456789012')
        ->and($order->user_id)->toBeNull()
        ->and($order->transaction_id)->toBeNull();
});
