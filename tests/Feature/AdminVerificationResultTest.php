<?php

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
