<?php

use App\Notifications\VerifyEmailOtpNotification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register and are redirected to otp email verification', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'account_type' => 'individual',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $user = \App\Models\User::where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('verification.notice', absolute: false));

    Notification::assertSentTo($user, VerifyEmailOtpNotification::class);

    expect($user->email_verified_at)->toBeNull()
        ->and($user->email_verification_otp)->not->toBeNull()
        ->and($user->email_verification_otp_expires_at)->not->toBeNull()
        ->and($user->customer->account_type)->toBe('individual');
});

test('business users must provide business registration profile fields', function () {
    Notification::fake();

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Business User',
        'email' => 'business@example.com',
        'account_type' => 'business',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors([
        'registration_number',
        'address',
        'website',
        'use_case',
        'expected_monthly_volume',
    ]);
});

test('business users can register with profile details', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Business User',
        'email' => 'business@example.com',
        'account_type' => 'business',
        'registration_number' => 'RC1234567',
        'address' => '12 Marina, Lagos',
        'website' => 'https://example.com',
        'use_case' => 'Customer onboarding and KYC checks',
        'expected_monthly_volume' => '101-500',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $user = \App\Models\User::where('email', 'business@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('verification.notice', absolute: false));

    expect($user->customer->account_type)->toBe('business')
        ->and($user->customer->registration_number)->toBe('RC1234567')
        ->and($user->customer->address)->toBe('12 Marina, Lagos')
        ->and($user->customer->website)->toBe('https://example.com')
        ->and($user->customer->use_case)->toBe('Customer onboarding and KYC checks')
        ->and($user->customer->expected_monthly_volume)->toBe('101-500');
});
