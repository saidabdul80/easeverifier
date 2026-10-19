<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaygoPaymentAttempt;
use App\Models\PaygoVerificationIntent;
use App\Models\PaystackGatewayAccount;
use RuntimeException;

class PaystackGatewayResolver
{
    public function forCustomer(?Customer $customer): ?PaystackGatewayAccount
    {
        if (! $customer) {
            return null;
        }

        return $customer->paystackGatewayAccounts()
            ->where('environment', $this->systemEnvironment())
            ->where('owner_type', 'customer')
            ->where('is_trusted', true)
            ->where('is_active', true)
            ->where('verification_status', 'verified')
            ->latest('id')
            ->first();
    }

    public function forIntent(PaygoVerificationIntent $intent): ?PaystackGatewayAccount
    {
        if ($intent->paystack_gateway_owner_type !== 'customer') {
            return null;
        }

        $gateway = $intent->paystackGatewayAccount;

        if (! $gateway) {
            throw new RuntimeException('The Paystack account used for this payment is no longer available.');
        }

        return $gateway;
    }

    public function forAttempt(PaygoPaymentAttempt $attempt): ?PaystackGatewayAccount
    {
        $gateway = $attempt->paystack_gateway_owner_type === 'customer'
            ? $attempt->paystackGatewayAccount
            : null;

        if ($attempt->paystack_gateway_owner_type === 'customer' && ! $gateway) {
            throw new RuntimeException('The Paystack account used for this payment is no longer available.');
        }

        $environment = $gateway?->environment ?? $this->systemEnvironment();
        $fingerprint = $gateway?->key_fingerprint ?? $this->systemFingerprint();

        if ((filled($attempt->paystack_environment) && $attempt->paystack_environment !== $environment)
            || (filled($attempt->paystack_key_fingerprint) && $attempt->paystack_key_fingerprint !== $fingerprint)) {
            throw new RuntimeException('The Paystack credentials for this payment have changed. Please contact support.');
        }

        return $gateway;
    }

    public function client(?PaystackGatewayAccount $gateway): PaystackService
    {
        return $gateway
            ? PaystackService::withCredentials($gateway->secret_key, $gateway->public_key)
            : app(PaystackService::class);
    }

    public function systemEnvironment(): string
    {
        $key = (string) config('services.paystack.secret_key');

        return str_starts_with($key, 'sk_test_') ? 'test' : 'live';
    }

    public function systemFingerprint(): ?string
    {
        $key = (string) config('services.paystack.secret_key');

        return filled($key) ? PaystackGatewayAccount::fingerprint($key) : null;
    }
}
