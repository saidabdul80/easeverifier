<?php

use App\Models\Customer;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('builds customer dashboard analytics from the signed-in customer records', function () {
    Role::findOrCreate('customer');

    $customer = User::factory()->create(['is_active' => true]);
    $customer->assignRole('customer');
    Customer::create([
        'user_id' => $customer->id,
        'company_name' => 'Dashboard Test Company',
    ]);

    $service = VerificationService::create([
        'name' => 'NIN Verification',
        'slug' => 'nin',
        'description' => 'Dashboard test service',
        'default_price' => 150,
        'cost_price' => 75,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-CUSTOMER-DASHBOARD',
        'search_parameter' => '22123456789',
        'amount_charged' => 150,
        'status' => 'completed',
        'source' => 'web',
        'completed_at' => now(),
    ]);

    $this->actingAs($customer)
        ->get(route('customer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Dashboard')
            ->where('stats.total_verifications', 1)
            ->where('stats.successful_verifications', 1)
            ->where('stats.total_spent', 150)
            ->has('activityTrend', 7)
            ->where('activityTrend.6.date', today()->toDateString())
            ->where('activityTrend.6.verifications', 1)
            ->where('activityTrend.6.completed', 1)
            ->where('activityTrend.6.spent', 150)
            ->has('recentVerifications', 1)
            ->where('recentVerifications.0.reference', 'VER-CUSTOMER-DASHBOARD'));
});
