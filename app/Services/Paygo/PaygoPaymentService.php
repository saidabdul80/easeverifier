<?php

namespace App\Services\Paygo;

use App\Models\CustomerPaygoService;
use App\Models\PaygoPaymentAttempt;
use App\Models\PaygoVerificationIntent;
use App\Models\Transaction;
use App\Services\PaystackGatewayResolver;
use App\Services\PaystackSplitService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaygoPaymentService
{
    public function __construct(
        protected PaystackGatewayResolver $gateways,
        protected PaystackSplitService $splits,
        protected PaygoVerificationService $paygo,
    ) {}

    public function checkout(CustomerPaygoService $service, PaygoVerificationIntent $intent, string $email, bool $retry = false): array
    {
        try {
            $intent->refresh();
            if ($this->isPaid($intent)) {
                return $this->paidResponse($intent);
            }

            $this->ensureOriginalAttempt($intent);
            $latest = $intent->paymentAttempts()->orderByDesc('attempt_number')->firstOrFail();

            if ($latest->status === 'reserved' || ($latest->status === 'initialization_failed' && $latest->attempt_number === 0)) {
                return $this->initialize($intent, $latest, $service, $email);
            }

            foreach ($intent->paymentAttempts()->orderBy('attempt_number')->get() as $attempt) {
                if ($attempt->status === 'initialization_failed') {
                    continue;
                }
                $payment = $this->verifyAttempt($attempt);
                $intent->refresh();
                if ($this->isPaid($intent)) {
                    return $this->paidResponse($intent);
                }
                if (in_array($payment['status'], ['pending', 'ongoing', 'processing', 'queued'], true)) {
                    return $this->failure('Your payment is still processing. Please check again shortly.', 'PAYMENT_PROCESSING');
                }
                if ($payment['status'] === 'not_found') {
                    if ($attempt->id === $latest->id && blank($attempt->authorization_url)
                        && $attempt->updated_at->lt(now()->subMinute())) {
                        return $this->initialize($intent, $attempt, $service, $email, true);
                    }

                    return $this->failure('We could not confirm this payment yet. Please check again shortly.', 'PAYMENT_STATE_UNKNOWN');
                }
                if (! in_array($payment['status'], ['failed', 'abandoned'], true)) {
                    return $this->failure('This payment needs review before another checkout can be created. Please contact support.', 'PAYMENT_REVIEW_REQUIRED');
                }
            }

            $latest->refresh();
            if ($latest->status === 'initialization_failed') {
                return $this->initialize($intent, $latest, $service, $email);
            }
            if (! $retry && $latest->status === 'abandoned' && filled($latest->authorization_url)) {
                return $this->checkoutResponse($latest, 'cached_checkout');
            }
            if (! $retry) {
                return $this->failure('This payment was not completed. Use Retry payment to start a replacement checkout.', 'PAYMENT_RETRY_REQUIRED');
            }
            if (! config('services.paystack.paygo_payment_retries_enabled')) {
                return $this->failure('Replacement checkout is temporarily unavailable. Please contact support.', 'PAYMENT_RETRIES_DISABLED');
            }

            $replacement = DB::transaction(function () use ($intent, $latest) {
                $locked = PaygoVerificationIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
                if ($this->isPaid($locked)) {
                    return null;
                }
                $current = $locked->paymentAttempts()->orderByDesc('attempt_number')->firstOrFail();
                if ($current->id !== $latest->id || ! in_array($current->status, ['failed', 'abandoned'], true)) {
                    throw new RuntimeException('A checkout is already being prepared. Please check again shortly.');
                }
                $number = $current->attempt_number;
                do {
                    $number++;
                    $reference = $locked->reference.'-paygo-'.$number;
                } while (PaygoPaymentAttempt::query()->where('reference', $reference)->exists()
                    || PaygoVerificationIntent::query()->where('reference', $reference)->exists()
                    || Transaction::query()->where('reference', $reference)->exists());

                return $locked->paymentAttempts()->create(array_merge($this->snapshot($current), [
                    'attempt_number' => $number,
                    'reference' => $reference,
                    'status' => 'reserved',
                ]));
            });

            return $replacement
                ? $this->initialize($intent, $replacement, $service, $email)
                : $this->paidResponse($intent->fresh());
        } catch (Throwable $exception) {
            return $this->failure(get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'We could not confirm your payment. Please try again shortly.', 'PAYMENT_STATE_UNKNOWN');
        }
    }

    public function ensureOriginalAttempt(PaygoVerificationIntent $intent): PaygoPaymentAttempt
    {
        return DB::transaction(function () use ($intent) {
            $locked = PaygoVerificationIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->paymentAttempts()->where('attempt_number', 0)->first();
            if ($existing) {
                return $existing;
            }

            $checkout = $locked->metadata['paystack_checkout'] ?? [];
            $legacy = filled($checkout['authorization_url'] ?? null) || filled($locked->paystack_environment)
                || $locked->created_at->lt(now()->subMinute());
            if (! $legacy && ! $this->isPaid($locked)) {
                $gateway = $this->gateways->forCustomer($locked->user?->customer);
                $locked->update([
                    'paystack_gateway_account_id' => $gateway?->id,
                    'paystack_gateway_owner_type' => $gateway ? 'customer' : 'system',
                    'paystack_environment' => $gateway?->environment ?? $this->gateways->systemEnvironment(),
                    'paystack_key_fingerprint' => $gateway?->key_fingerprint ?? $this->gateways->systemFingerprint(),
                    'settlement_strategy' => $gateway ? 'customer_gateway_system_subaccount' : 'system_gateway_customer_subaccount',
                ]);
            }

            return $locked->paymentAttempts()->create(array_merge($this->snapshot($locked), [
                'attempt_number' => 0,
                'reference' => $locked->reference,
                'status' => $this->isPaid($locked) ? 'success' : ($legacy ? 'unknown' : 'reserved'),
                'authorization_url' => $checkout['authorization_url'] ?? null,
                'access_code' => $checkout['access_code'] ?? null,
                'initialized_at' => $checkout['initialized_at'] ?? null,
                'paid_at' => $locked->paid_at,
                'metadata' => ['paystack_split' => $locked->metadata['paystack_split'] ?? null, 'imported_legacy' => $legacy],
            ]));
        });
    }

    public function verifyReference(string $reference): array
    {
        $attempt = PaygoPaymentAttempt::query()->where('reference', $reference)->first();
        if (! $attempt) {
            $intent = PaygoVerificationIntent::query()->where('reference', $reference)->firstOrFail();
            $attempt = $this->ensureOriginalAttempt($intent);
        }

        return $this->verifyAttempt($attempt);
    }

    public function verifyAttempt(PaygoPaymentAttempt $attempt): array
    {
        try {
            $gateway = $this->gateways->forAttempt($attempt);
            $payment = $this->gateways->client($gateway)->verifyTransaction($attempt->reference);
        } catch (Throwable $exception) {
            $this->recordVerificationError($attempt, 'Payment verification is temporarily unavailable. Please check again shortly.');
            throw new RuntimeException('Payment verification is temporarily unavailable. Please check again shortly.', previous: $exception);
        }

        if (! ($payment['success'] ?? false)) {
            $notFound = in_array($payment['http_status'] ?? null, [400, 404], true)
                && str_contains(strtolower($payment['message'] ?? ''), 'reference not found');
            if ($notFound && blank($attempt->authorization_url)) {
                return ['success' => true, 'status' => 'not_found', 'reference' => $attempt->reference];
            }
            $this->recordVerificationError($attempt, 'We could not confirm your payment status. Please check again shortly.');
            throw new RuntimeException('We could not confirm your payment status. Please check again shortly.');
        }
        if (($payment['reference'] ?? null) !== $attempt->reference
            || (filled($payment['currency'] ?? null) && $payment['currency'] !== 'NGN')
            || (filled($payment['environment'] ?? null) && filled($attempt->paystack_environment)
                && $payment['environment'] !== $attempt->paystack_environment)) {
            $this->recordVerificationError($attempt, 'The payment verification details do not match this checkout.');
            throw new RuntimeException('The payment verification details do not match this checkout.');
        }
        if ($payment['status'] === 'success') {
            try {
                $this->paygo->completePayment($attempt->reference, $payment);
            } catch (RuntimeException $exception) {
                $this->recordVerificationError($attempt, 'The payment could not be reconciled. Please contact support.');
                throw $exception;
            }
        } else {
            DB::transaction(function () use ($attempt, $payment) {
                $locked = PaygoPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'success') {
                    $locked->update(['status' => $payment['status'], 'last_verified_at' => now(), 'last_error' => null]);
                }
            });
        }

        return $payment;
    }

    public function webhookSecret(string $reference): ?string
    {
        $attempt = PaygoPaymentAttempt::query()->where('reference', $reference)->first();
        $intent = $attempt?->intent ?? PaygoVerificationIntent::query()->where('reference', $reference)->first();
        if (! $intent) {
            return null;
        }
        $gateway = $attempt ? $this->gateways->forAttempt($attempt) : $this->gateways->forIntent($intent);

        return $gateway?->secret_key ?? config('services.paystack.secret_key');
    }

    private function initialize(PaygoVerificationIntent $intent, PaygoPaymentAttempt $attempt, CustomerPaygoService $service, string $email, bool $confirmedMissing = false): array
    {
        $claimed = DB::transaction(function () use ($intent, $attempt, $confirmedMissing) {
            $lockedIntent = PaygoVerificationIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            if ($this->isPaid($lockedIntent)) {
                return false;
            }
            $locked = PaygoPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['reserved', 'initialization_failed'], true)
                && ! ($confirmedMissing && blank($locked->authorization_url) && $locked->updated_at->lt(now()->subMinute()))) {
                throw new RuntimeException('A checkout is already being prepared. Please check again shortly.');
            }
            $locked->update(['status' => 'initializing', 'last_error' => null]);

            return true;
        });
        if (! $claimed) {
            return $this->paidResponse($intent->fresh());
        }

        $initializationSent = false;
        try {
            $gateway = $this->gateways->forAttempt($attempt);
            if ($gateway && ! $gateway->isReady()) {
                throw new RuntimeException('The original Paystack account is not available for a new checkout. Please contact support.');
            }
            $amountInKobo = (int) round((float) $attempt->amount * 100);
            $split = $this->splits->buildDynamicFlatSplit(
                $service->user?->customer, $amountInKobo, $attempt->reference, $gateway,
                (int) round((float) $attempt->system_price_snapshot * 100),
            );
            $attempt->update(['metadata' => array_merge($attempt->metadata ?? [], ['paystack_split' => $split['metadata'] ?? null])]);
            $initializationSent = true;
            $payment = $this->gateways->client($gateway)->initializeTransaction(
                email: $email,
                amountInKobo: $amountInKobo,
                reference: $attempt->reference,
                callbackUrl: route('paygo.callback'),
                options: $split ? $split['payment_options'] : [],
            );
        } catch (Throwable $exception) {
            $message = ! $initializationSent && get_class($exception) === RuntimeException::class
                ? $exception->getMessage()
                : 'Checkout initialization could not be confirmed. Please check again shortly.';
            $this->recordInitializationFailure($attempt, $initializationSent ? 'unknown' : 'initialization_failed',
                $message);
            if ($this->isPaid($intent->fresh())) {
                return $this->paidResponse($intent->fresh());
            }

            return $this->failure($message, $initializationSent ? 'PAYMENT_STATE_UNKNOWN' : 'INITIALIZATION_FAILED');
        }

        if (! ($payment['success'] ?? false)) {
            $message = (string) ($payment['message'] ?? 'Payment gateway initialization failed.');
            $duplicate = str_contains(strtolower($message), 'duplicate') && str_contains(strtolower($message), 'reference');
            $uncertain = $duplicate || ($payment['http_status'] ?? 500) >= 500
                || in_array($payment['http_status'] ?? null, [408, 409, 425, 429], true);
            $this->recordInitializationFailure($attempt, $uncertain ? 'unknown' : 'initialization_failed', $message);
            if ($this->isPaid($intent->fresh())) {
                return $this->paidResponse($intent->fresh());
            }
            if ($duplicate) {
                $verified = $this->verifyAttempt($attempt);
                if ($verified['status'] === 'success') {
                    return $this->paidResponse($intent->fresh());
                }
            }

            return $this->failure($uncertain ? 'We could not confirm this checkout. Please check payment status again shortly.' : $message,
                $uncertain ? 'PAYMENT_STATE_UNKNOWN' : 'INITIALIZATION_FAILED');
        }
        if (blank($payment['authorization_url'] ?? null) || ($payment['reference'] ?? null) !== $attempt->reference) {
            $this->recordInitializationFailure($attempt, 'unknown', 'Checkout response did not match the reserved payment reference.');

            return $this->failure('We could not confirm this checkout. Please check again shortly.', 'PAYMENT_STATE_UNKNOWN');
        }

        DB::transaction(function () use ($intent, $attempt, $payment) {
            $lockedIntent = PaygoVerificationIntent::query()->whereKey($intent->id)->lockForUpdate()->firstOrFail();
            $locked = PaygoPaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status' => $locked->status === 'success' ? 'success' : 'initialized',
                'authorization_url' => $payment['authorization_url'],
                'access_code' => $payment['access_code'] ?? null,
                'initialized_at' => now(), 'last_error' => null,
            ]);
            if (! $this->isPaid($lockedIntent)) {
                $lockedIntent->update([
                    'status' => 'pending',
                    'metadata' => array_merge($lockedIntent->metadata ?? [], [
                        'payment_status' => 'pending',
                        'paystack_split' => $locked->metadata['paystack_split'] ?? null,
                        'paystack_checkout' => [
                            'authorization_url' => $locked->authorization_url, 'access_code' => $locked->access_code,
                            'reference' => $locked->reference, 'amount' => (float) $locked->amount,
                            'system_price' => (float) $locked->system_price_snapshot, 'initialized_at' => now()->toISOString(),
                        ],
                    ]),
                ]);
            }
        });

        return $this->isPaid($intent->fresh()) ? $this->paidResponse($intent->fresh()) : $this->checkoutResponse($attempt->fresh(), 'initialized');
    }

    private function snapshot(PaygoPaymentAttempt|PaygoVerificationIntent $source): array
    {
        return array_intersect_key($source->getAttributes(), array_flip([
            'paystack_gateway_account_id', 'paystack_gateway_owner_type', 'paystack_environment',
            'paystack_key_fingerprint', 'settlement_strategy', 'amount', 'system_price_snapshot',
        ]));
    }

    private function recordInitializationFailure(PaygoPaymentAttempt $attempt, string $status, string $message): void
    {
        PaygoPaymentAttempt::query()->whereKey($attempt->id)->where('status', '!=', 'success')
            ->update(['status' => $status, 'last_error' => $message]);
    }

    private function recordVerificationError(PaygoPaymentAttempt $attempt, string $message): void
    {
        PaygoPaymentAttempt::query()->whereKey($attempt->id)->where('status', '!=', 'success')
            ->update(['last_verified_at' => now(), 'last_error' => $message]);
    }

    private function isPaid(PaygoVerificationIntent $intent): bool
    {
        return $intent->paid_at !== null || in_array($intent->status, ['paid', 'verifying', 'used'], true);
    }

    private function paidResponse(PaygoVerificationIntent $intent): array
    {
        $reference = $intent->metadata['payment_reference'] ?? $intent->reference;

        return ['success' => true, 'paid' => true, 'reference' => $reference,
            'authorization_url' => route('paygo.callback', ['reference' => $reference]), 'access_code' => null];
    }

    private function checkoutResponse(PaygoPaymentAttempt $attempt, string $servedFrom): array
    {
        return ['success' => true, 'paid' => false, 'reference' => $attempt->reference,
            'authorization_url' => $attempt->authorization_url, 'access_code' => $attempt->access_code, 'served_from' => $servedFrom];
    }

    private function failure(string $message, string $code): array
    {
        return ['success' => false, 'message' => $message, 'error_code' => $code];
    }
}
