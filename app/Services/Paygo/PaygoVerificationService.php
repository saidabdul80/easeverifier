<?php

namespace App\Services\Paygo;

use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\PaygoWallet;
use App\Models\VerificationRequest;
use App\Services\Verification\VerificationEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaygoVerificationService
{
    private const MAX_VERIFICATION_ATTEMPTS = 3;

    public function __construct(
        protected VerificationEngine $verificationEngine,
    ) {}

    public function createIntent(CustomerPaygoService $paygoService, array $data, ?string $ipAddress = null): PaygoVerificationIntent
    {
        $paygoService->loadMissing(['user.wallet', 'verificationService']);

        if (! $paygoService->is_active || ! $paygoService->verificationService?->is_active) {
            throw new RuntimeException('This pay-on-the-go service is not available.');
        }

        $systemPrice = (float) $paygoService->user->getPriceForService($paygoService->verificationService);
        $publicPrice = (float) $paygoService->price;

        if ($publicPrice <= $systemPrice) {
            throw new RuntimeException('This pay-on-the-go service price is below the allowed minimum.');
        }

        $nin = $this->normalizeNin($data['nin']);
        $reference = PaygoVerificationIntent::generateReference();

        return DB::transaction(function () use ($paygoService, $data, $ipAddress, $nin, $reference, $publicPrice, $systemPrice) {
            $intent = PaygoVerificationIntent::create([
                'customer_paygo_service_id' => $paygoService->id,
                'user_id' => $paygoService->user_id,
                'verification_service_id' => $paygoService->verification_service_id,
                'reference' => $reference,
                'nin_hash' => PaygoVerificationIntent::hashNin($nin),
                'amount' => $publicPrice,
                'system_price_snapshot' => $systemPrice,
                'status' => 'pending',
                'verification_attempts' => 0,
                'buyer_phone' => $data['phone'] ?? null,
                'expires_at' => now()->addHours(24),
                'metadata' => [
                    'nin_last4' => substr($nin, -4),
                    'initiated_ip' => $ipAddress,
                    'payment_gateway' => 'paystack',
                    'payment_status' => 'pending',
                ],
            ]);

            return $intent->fresh(['paygoService']);
        });
    }

    public function findPaidUnusedIntent(CustomerPaygoService $paygoService, string $nin): ?PaygoVerificationIntent
    {
        return PaygoVerificationIntent::query()
            ->where('customer_paygo_service_id', $paygoService->id)
            ->where('nin_hash', PaygoVerificationIntent::hashNin($this->normalizeNin($nin)))
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public function completePayment(string $reference, array $paymentData): PaygoVerificationIntent
    {
        return DB::transaction(function () use ($reference, $paymentData) {
            $intent = PaygoVerificationIntent::where('reference', $reference)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) ($paymentData['amount'] ?? 0) < (float) $intent->amount) {
                $intent->update([
                    'status' => 'failed',
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'payment_status' => 'amount_mismatch',
                        'paid_amount' => $paymentData['amount'] ?? null,
                    ]),
                ]);

                throw new RuntimeException('Paid amount is lower than the expected PayGo amount.');
            }

            if (in_array($intent->status, ['pending', 'failed'], true)) {
                $margin = max(0, (float) $intent->amount - (float) $intent->system_price_snapshot);
                $paygoWallet = PaygoWallet::firstOrCreate(
                    ['user_id' => $intent->user_id],
                    ['balance' => 0, 'pending_withdrawal' => 0, 'currency' => 'NGN', 'is_active' => true],
                );

                $earningTransaction = $paygoWallet->credit(
                    $margin,
                    "PayGo earning for {$intent->reference}",
                    [
                        'paygo_intent_id' => $intent->id,
                        'customer_paygo_service_id' => $intent->customer_paygo_service_id,
                        'payment_reference' => $paymentData['reference'] ?? $reference,
                        'payment_amount' => (float) $intent->amount,
                        'system_price' => (float) $intent->system_price_snapshot,
                    ],
                );

                $intent->update([
                    'status' => 'paid',
                    'transaction_id' => null,
                    'paid_at' => $paymentData['paid_at'] ?? now(),
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'payment_gateway' => 'paystack',
                        'payment_status' => 'success',
                        'payment_reference' => $paymentData['reference'] ?? $reference,
                        'payment_channel' => $paymentData['channel'] ?? null,
                        'paygo_wallet_id' => $paygoWallet->id,
                        'paygo_wallet_transaction_id' => $earningTransaction?->id,
                        'paygo_earning' => $margin,
                        'system_price' => (float) $intent->system_price_snapshot,
                    ]),
                ]);
            }

            return $intent->fresh(['paygoService', 'transaction']);
        });
    }

    public function verifyPaidIntent(CustomerPaygoService $paygoService, array $data, ?string $ipAddress = null): array
    {
        $nin = $this->normalizeNin($data['nin']);
        $reference = $data['reference'] ?? null;

        $intent = DB::transaction(function () use ($paygoService, $reference, $nin) {
            $intentQuery = PaygoVerificationIntent::where('customer_paygo_service_id', $paygoService->id)
                ->where('nin_hash', PaygoVerificationIntent::hashNin($nin));

            if ($reference) {
                $intentQuery->where('reference', $reference);
            } else {
                $intentQuery
                    ->where('status', 'paid')
                    ->where(function ($query) {
                        $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            }

            $intent = $intentQuery
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $intent) {
                throw new RuntimeException('No matching paid NIN verification payment was found.');
            }

            if ($intent->expires_at && now()->greaterThan($intent->expires_at) && $intent->status !== 'used') {
                $intent->update(['status' => 'expired']);
                throw new RuntimeException('This NIN verification payment has expired.');
            }

            if ($intent->status === 'used') {
                throw new RuntimeException('This NIN verification payment has already been used.');
            }

            if ($intent->status !== 'paid') {
                throw new RuntimeException('Payment has not been completed for this NIN verification.');
            }

            if ($intent->verification_attempts >= self::MAX_VERIFICATION_ATTEMPTS) {
                $intent->update([
                    'status' => 'used',
                    'used_at' => $intent->used_at ?? now(),
                ]);

                throw new RuntimeException('This NIN verification payment has already been used.');
            }

            $intent->update(['status' => 'verifying']);

            return $intent->fresh(['user', 'verificationService']);
        });

        $existingVerification = null;

        if ($intent->verification_attempts > 0) {
            $existingVerification = VerificationRequest::where('user_id', $paygoService->user_id)
                ->whereNull('branch_id')
                ->where('verification_service_id', $paygoService->verification_service_id)
                ->where('search_parameter', $nin)
                ->where('status', 'completed')
                ->whereNotNull('response_data')
                ->with('serviceProvider:id,updated_at')
                ->latest()
                ->first();
        }

        if ($existingVerification?->canReuseResponseData()) {
            $intent = $this->recordVerificationAttempt($intent, $existingVerification, [
                'verification_status' => 'cached',
                'verification_reference' => $existingVerification->reference,
            ]);

            return [
                'success' => true,
                'data' => $existingVerification->response_data,
                'response_time' => 0,
                'attempts_remaining' => max(0, self::MAX_VERIFICATION_ATTEMPTS - $intent->verification_attempts),
            ];
        }

        $result = $this->verificationEngine->verify(
            user: $intent->user,
            service: $intent->verificationService,
            searchParameter: $nin,
            source: 'api',
            ipAddress: $ipAddress,
            chargeWallet: false,
            amountCharged: (float) $intent->system_price_snapshot,
        );

        $verification = VerificationRequest::query()
            ->where('user_id', $paygoService->user_id)
            ->where('verification_service_id', $paygoService->verification_service_id)
            ->where('search_parameter', $nin)
            ->latest('id')
            ->first();

        if ($result->isSuccessful()) {
            $intent = $this->recordVerificationAttempt($intent, $verification, [
                'verification_status' => 'completed',
                'verification_reference' => $verification?->reference,
            ]);

            return [
                'success' => true,
                'data' => $result->getData(),
                'response_time' => $result->responseTime,
                'attempts_remaining' => max(0, self::MAX_VERIFICATION_ATTEMPTS - $intent->verification_attempts),
            ];
        }

        if ($this->isConsumableFailure($result->getErrorMessage(), $result->errorCode)) {
            $this->recordVerificationAttempt($intent, $verification, [
                'verification_status' => 'not_found',
                'verification_reference' => $verification?->reference,
                'error_code' => $result->errorCode,
            ]);
        } else {
            $intent->update([
                'status' => 'paid',
                'verification_request_id' => $verification?->id,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'retryable_failed',
                    'verification_reference' => $verification?->reference,
                    'error_code' => $result->errorCode,
                ]),
            ]);
        }

        return [
            'success' => false,
            'error' => $result->getErrorMessage(),
            'error_code' => $result->errorCode,
        ];
    }

    protected function recordVerificationAttempt(
        PaygoVerificationIntent $intent,
        ?VerificationRequest $verification,
        array $metadata
    ): PaygoVerificationIntent {
        return DB::transaction(function () use ($intent, $verification, $metadata) {
            $lockedIntent = PaygoVerificationIntent::whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempts = min(self::MAX_VERIFICATION_ATTEMPTS, $lockedIntent->verification_attempts + 1);
            $isUsed = $attempts >= self::MAX_VERIFICATION_ATTEMPTS;

            $lockedIntent->update([
                'status' => $isUsed ? 'used' : 'paid',
                'verification_attempts' => $attempts,
                'verification_request_id' => $verification?->id ?? $lockedIntent->verification_request_id,
                'used_at' => $isUsed ? now() : null,
                'metadata' => array_merge($lockedIntent->metadata ?? [], $metadata, [
                    'verification_attempts' => $attempts,
                    'attempts_remaining' => max(0, self::MAX_VERIFICATION_ATTEMPTS - $attempts),
                ]),
            ]);

            return $lockedIntent->fresh();
        });
    }

    public function normalizeNin(string $nin): string
    {
        return preg_replace('/\D+/', '', $nin);
    }

    protected function isConsumableFailure(?string $message, ?string $code): bool
    {
        $message = strtolower($message ?? '');
        $code = strtolower($code ?? '');

        foreach (['not found', 'no record', 'record not found', 'does not exist', 'invalid', 'no data'] as $pattern) {
            if (str_contains($message, $pattern) || str_contains($code, $pattern)) {
                return true;
            }
        }

        return str_contains($code, '404') || str_contains($code, 'not_found') || str_contains($code, 'no_record');
    }
}
