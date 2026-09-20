<?php

use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createAdminVerificationUser(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create([
        'is_active' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

it('lets admins view verification results with the provided inputs', function () {
    $admin = createAdminVerificationUser('admin');
    $customer = createAdminVerificationUser('customer');
    $service = VerificationService::updateOrCreate(
        ['slug' => 'neco-everify-result-fetch'],
        [
            'name' => 'NECO e-Verify Result Fetch',
            'description' => 'Test result service',
            'default_price' => 100,
            'cost_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ],
    );

    $verification = VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-ADMIN-INPUTS-001',
        'search_parameter' => '2410896226BC',
        'request_data' => [
            'board' => 'neco-everify',
            'action' => 'fetch',
            'parameters' => [
                'token' => 'real-token',
                'examno' => '2410896226BC',
                'exam_year' => '2024',
                'exam_type' => 'SSCEInt',
            ],
            'customer_parameters' => [
                'token' => '***REDACTED***',
                'examno' => '2410896226BC',
                'exam_year' => '2024',
                'exam_type' => 'SSCEInt',
            ],
        ],
        'response_data' => null,
        'amount_charged' => 100,
        'status' => 'failed',
        'source' => 'web',
        'error_message' => 'Invalid token or token has not been verified!',
        'completed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.verifications.show', $verification))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Verifications/Show')
            ->where('verification.reference', 'VER-ADMIN-INPUTS-001')
            ->where('service.name', 'NECO e-Verify Result Fetch')
            ->where('providedInputs.token', 'real-token')
            ->where('providedInputs.examno', '2410896226BC')
            ->where('providedInputs.exam_year', '2024')
            ->where('result.error_message', 'Invalid token or token has not been verified!')
        );
});

it('does not crash the admin dashboard when the source override table has not been migrated', function () {
    $admin = createAdminVerificationUser('admin');
    $customer = createAdminVerificationUser('customer');
    $service = VerificationService::updateOrCreate(
        ['slug' => 'nin'],
        [
            'name' => 'NIN Verification',
            'description' => 'Test service',
            'default_price' => 100,
            'cost_price' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ],
    );

    VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-DASH-001',
        'search_parameter' => '12345678901',
        'request_data' => [],
        'response_data' => [],
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'api',
        'completed_at' => now(),
    ]);

    Schema::dropIfExists('verification_request_source_overrides');

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('recentVerifications.0.reference', 'VER-DASH-001')
        );
});

it('filters admin PayGo analytics and recent payments from the same scope', function () {
    $admin = createAdminVerificationUser('admin');
    $customer = createAdminVerificationUser('customer');
    $otherCustomer = createAdminVerificationUser('customer');
    $service = VerificationService::updateOrCreate(
        ['slug' => 'waec-result-fetch'],
        ['name' => 'WAEC Result Fetch', 'description' => 'Test', 'default_price' => 200, 'cost_price' => 100, 'is_active' => true, 'sort_order' => 1],
    );
    $paygoService = CustomerPaygoService::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'name' => 'WAEC Result Verification',
        'public_slug' => CustomerPaygoService::generatePublicSlug('WAEC Result Verification'),
        'verify_secret_hash' => hash('sha256', CustomerPaygoService::generateSecret()),
        'price' => 300,
        'reference_price' => 1000,
        'is_active' => true,
    ]);

    $intentData = [
        'customer_paygo_service_id' => $paygoService->id,
        'verification_service_id' => $service->id,
        'flow_type' => 'result_reference',
        'nin_hash' => null,
        'lookup_hash' => PaygoVerificationIntent::hashLookup('waec:4271710002'),
        'lookup_label' => 'WAEC 4271710002',
        'payload' => [],
        'amount' => 1000,
        'system_price_snapshot' => 550,
        'verification_attempts' => 0,
        'max_fetches_snapshot' => 2,
        'reference_fetches' => 0,
        'metadata' => [],
    ];

    PaygoVerificationIntent::create(array_merge($intentData, [
        'user_id' => $customer->id,
        'reference' => 'QAP-ADMIN-FILTER-PAID',
        'status' => 'paid',
        'paid_at' => now(),
    ]));
    PaygoVerificationIntent::create(array_merge($intentData, [
        'user_id' => $customer->id,
        'reference' => 'QAP-ADMIN-FILTER-PENDING',
        'status' => 'pending',
    ]));
    PaygoVerificationIntent::create(array_merge($intentData, [
        'user_id' => $otherCustomer->id,
        'reference' => 'QAP-OTHER-CUSTOMER',
        'status' => 'paid',
        'paid_at' => now(),
    ]));

    $url = route('admin.dashboard', [
        'paygo_customer' => $customer->id,
        'paygo_status' => 'paid',
        'paygo_package' => 'reference',
        'paygo_date_from' => today()->toDateString(),
        'paygo_date_to' => today()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('paygoStats.total_payments', 1)
            ->where('paygoStats.successful_payments', 1)
            ->where('paygoStats.gross_revenue', 1000)
            ->where('paygoStats.system_settlement', 550)
            ->where('paygoStats.customer_earnings', 450)
            ->where('paygoStats.reference_packages', 1)
            ->where('paygoStats.conversion_rate', 100)
            ->has('platformTrend', 7)
            ->has('paygoTrend', 7)
            ->where('paygoTrend.6.date', today()->toDateString())
            ->where('paygoTrend.6.gross', 1000)
            ->where('paygoTrend.6.settlement', 550)
            ->where('paygoTrend.6.earnings', 450)
            ->where('paygoTrend.6.payments', 1)
            ->has('recentPaygo', 1)
            ->where('recentPaygo.0.reference', 'QAP-ADMIN-FILTER-PAID'));
});
