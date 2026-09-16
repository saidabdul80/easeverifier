<?php

use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createVerificationSourceFallbackUser(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

function createVerificationSourceFallbackService(): VerificationService
{
    return VerificationService::create([
        'name' => 'NIN Lookup',
        'slug' => 'nin-lookup',
        'description' => 'Test service',
        'default_price' => 100,
        'cost_price' => 50,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

it('falls back to the stored source when the override table is unavailable', function () {
    $user = createVerificationSourceFallbackUser('customer');
    $service = createVerificationSourceFallbackService();

    Schema::dropIfExists('verification_request_source_overrides');

    $verification = VerificationRequest::create([
        'user_id' => $user->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-SOURCE-FALLBACK-001',
        'search_parameter' => '12345678901',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'paygo',
    ])->fresh();

    expect($verification->source)->toBe('api');
})->group('Billing');

it('keeps the admin verification pages accessible when the override table is unavailable', function () {
    $admin = createVerificationSourceFallbackUser('admin');
    $customer = createVerificationSourceFallbackUser('customer');
    $service = createVerificationSourceFallbackService();

    $verification = VerificationRequest::create([
        'user_id' => $customer->id,
        'verification_service_id' => $service->id,
        'reference' => 'VER-SOURCE-FALLBACK-002',
        'search_parameter' => '12345678901',
        'amount_charged' => 100,
        'status' => 'completed',
        'source' => 'api',
    ]);

    Schema::dropIfExists('verification_request_source_overrides');

    $this->actingAs($admin)
        ->get(route('admin.verifications.index', ['source' => 'api']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.verifications.index', ['source' => 'paygo']))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.verifications.show', $verification))
        ->assertOk();
})->group('Billing');
