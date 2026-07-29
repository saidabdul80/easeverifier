<?php

namespace App\Services\Paygo;

use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\PaygoWallet;
use App\Models\VerificationRequest;
use App\Services\ResultVerify\ResultVerificationEngine;
use App\Services\Verification\VerificationEngine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaygoVerificationService
{
    private const MAX_VERIFICATION_ATTEMPTS = 3;

    public function __construct(
        protected VerificationEngine $verificationEngine,
        protected ResultVerificationEngine $resultVerificationEngine,
    ) {}

    public function createIntent(CustomerPaygoService $paygoService, array $data, ?string $ipAddress = null): PaygoVerificationIntent
    {
        $paygoService->loadMissing(['user.wallet', 'user.customer', 'verificationService']);

        if (! $paygoService->is_active || ! $paygoService->verificationService?->is_active) {
            throw new RuntimeException('This pay-on-the-go service is not available.');
        }

        $systemPrice = (float) $paygoService->user->getPriceForService($paygoService->verificationService);
        $publicPrice = (float) $paygoService->price;

        if ($publicPrice <= $systemPrice) {
            throw new RuntimeException('This pay-on-the-go service price is below the allowed minimum.');
        }

        $isResultFlow = $paygoService->isResultVerification();
        $maxFetches = $this->maxFetchesFor($paygoService);
        $portalContext = $isResultFlow ? $this->portalContextForIntent($paygoService, $data['portal_context'] ?? []) : [];
        $lookup = $isResultFlow
            ? $this->resultSearchParameter($paygoService, $data['params'] ?? [])
            : $this->normalizeNin($data['nin']);

        if ($lookup === '') {
            throw new RuntimeException('A valid lookup value is required for this PayGo service.');
        }

        $reference = PaygoVerificationIntent::generateReference();

        return DB::transaction(function () use ($paygoService, $data, $ipAddress, $isResultFlow, $maxFetches, $portalContext, $lookup, $reference, $publicPrice, $systemPrice) {
            $intent = PaygoVerificationIntent::create([
                'customer_paygo_service_id' => $paygoService->id,
                'user_id' => $paygoService->user_id,
                'verification_service_id' => $paygoService->verification_service_id,
                'flow_type' => $isResultFlow ? 'result' : 'identity',
                'reference' => $reference,
                'nin_hash' => $isResultFlow ? null : PaygoVerificationIntent::hashNin($lookup),
                'lookup_hash' => PaygoVerificationIntent::hashLookup($paygoService->id.':'.$lookup),
                'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                'payload' => $isResultFlow ? ($data['params'] ?? []) : null,
                'amount' => $publicPrice,
                'system_price_snapshot' => $systemPrice,
                'status' => 'pending',
                'verification_attempts' => 0,
                'max_fetches_snapshot' => $maxFetches,
                'reference_fetches' => 0,
                'buyer_phone' => $data['phone'] ?? null,
                'expires_at' => now()->addHours(24),
                'metadata' => array_merge([
                    'flow_type' => $isResultFlow ? 'result' : 'identity',
                    'nin_last4' => $isResultFlow ? null : substr($lookup, -4),
                    'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                    'buyer_email' => $data['email'] ?? null,
                    'initiated_ip' => $ipAddress,
                    'payment_gateway' => 'paystack',
                    'payment_status' => 'pending',
                ], $portalContext),
            ]);

            return $intent->fresh(['paygoService']);
        });
    }

    public function findPaidUnusedIntent(CustomerPaygoService $paygoService, string $nin): ?PaygoVerificationIntent
    {
        $normalizedNin = $this->normalizeNin($nin);

        return PaygoVerificationIntent::query()
            ->where('customer_paygo_service_id', $paygoService->id)
            ->where(function ($query) use ($paygoService, $normalizedNin) {
                $query
                    ->where('lookup_hash', PaygoVerificationIntent::hashLookup($paygoService->id.':'.$normalizedNin))
                    ->orWhere('nin_hash', PaygoVerificationIntent::hashNin($normalizedNin));
            })
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
                ->where(function ($query) use ($paygoService, $nin) {
                    $query
                        ->where('lookup_hash', PaygoVerificationIntent::hashLookup($paygoService->id.':'.$nin))
                        ->orWhere('nin_hash', PaygoVerificationIntent::hashNin($nin));
                });

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

            $maxAttempts = $this->maxFetchesForIntent($intent);

            if ($intent->verification_attempts >= $maxAttempts) {
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
                'attempts_remaining' => max(0, $this->maxFetchesForIntent($intent) - $intent->verification_attempts),
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
                'attempts_remaining' => max(0, $this->maxFetchesForIntent($intent) - $intent->verification_attempts),
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

            $maxAttempts = $this->maxFetchesForIntent($lockedIntent);
            $attempts = min($maxAttempts, $lockedIntent->verification_attempts + 1);
            $isUsed = $attempts >= $maxAttempts;

            $lockedIntent->update([
                'status' => $isUsed ? 'used' : 'paid',
                'verification_attempts' => $attempts,
                'verification_request_id' => $verification?->id ?? $lockedIntent->verification_request_id,
                'used_at' => $isUsed ? now() : null,
                'metadata' => array_merge($lockedIntent->metadata ?? [], $metadata, [
                    'verification_attempts' => $attempts,
                    'attempts_remaining' => max(0, $maxAttempts - $attempts),
                ]),
            ]);

            return $lockedIntent->fresh();
        });
    }

    public function fetchResultForPaidIntent(PaygoVerificationIntent $intent, ?string $ipAddress = null): array
    {
        $intent->loadMissing(['paygoService.user.customer', 'verificationService', 'verificationRequest']);

        if (! $intent->isResultFlow() || ! $intent->paygoService?->isResultVerification()) {
            throw new RuntimeException('This PayGo payment is not for result verification.');
        }

        if ($intent->expires_at && now()->greaterThan($intent->expires_at) && $intent->status !== 'used') {
            $intent->update(['status' => 'expired']);
            throw new RuntimeException('This result verification payment has expired.');
        }

        if (! in_array($intent->status, ['paid', 'verifying'], true)) {
            throw new RuntimeException('Payment has not been completed for this result verification.');
        }

        $completedVerification = $this->resolveCompletedResultVerification($intent);

        if ($completedVerification) {
            return [
                'success' => true,
                'data' => $completedVerification->response_data,
                'response_time' => 0,
                'verification' => $completedVerification,
            ];
        }

        $params = $intent->payload ?? [];
        if ($params === []) {
            throw new RuntimeException('No result verification form data was found for this payment.');
        }

        $board = $intent->paygoService->resultBoard();
        if (! $board) {
            throw new RuntimeException('Unable to determine the result board for this PayGo service.');
        }

        $intent->update(['status' => 'verifying']);

        $result = $this->resultVerificationEngine->verify(
            user: $intent->user,
            board: $board,
            params: $params,
            source: 'paygo',
            ipAddress: $ipAddress,
            chargeWallet: false,
            amountCharged: (float) $intent->system_price_snapshot,
        );

        $searchParameter = $this->resultSearchParameter($intent->paygoService, $params);
        $verification = VerificationRequest::query()
            ->where('user_id', $intent->user_id)
            ->where('verification_service_id', $intent->verification_service_id)
            ->where('search_parameter', $searchParameter)
            ->latest('id')
            ->first();

        if ($result->isSuccessful()) {
            $intent->update([
                'status' => 'paid',
                'verification_request_id' => $verification?->id,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'completed',
                    'verification_reference' => $verification?->reference,
                    'result_fetched_at' => now()->toISOString(),
                ]),
            ]);

            return [
                'success' => true,
                'data' => $result->getData(),
                'response_time' => $result->responseTime,
                'verification' => $verification,
            ];
        }

        $intent->update([
            'status' => 'paid',
            'verification_request_id' => $verification?->id,
            'metadata' => array_merge($intent->metadata ?? [], [
                'verification_status' => 'failed',
                'verification_reference' => $verification?->reference,
                'error_code' => $result->errorCode,
                'error_message' => $result->getErrorMessage(),
            ]),
        ]);

        return [
            'success' => false,
            'error' => $result->getErrorMessage(),
            'error_code' => $result->errorCode,
            'verification' => $verification,
        ];
    }

    public function displayResultByReference(string $reference): PaygoVerificationIntent
    {
        $intent = PaygoVerificationIntent::query()
            ->with(['paygoService.user.customer', 'verificationService', 'verificationRequest'])
            ->where('reference', $reference)
            ->firstOrFail();

        if (! $intent->isResultFlow()) {
            throw new RuntimeException('This reference is not for a PayGo result verification.');
        }

        return $intent;
    }

    public function pullResultByReference(string $reference): array
    {
        return DB::transaction(function () use ($reference) {
            $intent = PaygoVerificationIntent::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $intent || ! $intent->isResultFlow()) {
                throw new RuntimeException('Result reference was not found.');
            }

            $intent->loadMissing(['verificationRequest', 'paygoService']);

            if ($intent->status === 'used' || $intent->reference_fetches >= $this->maxFetchesForIntent($intent)) {
                $intent->update([
                    'status' => 'used',
                    'used_at' => $intent->used_at ?? now(),
                ]);

                throw new RuntimeException('This result reference has reached its configured pull limit.');
            }

            if ($intent->status !== 'paid') {
                throw new RuntimeException('Result payment has not been completed.');
            }

            $verification = $this->resolveCompletedResultVerification($intent);

            if (! $verification) {
                throw new RuntimeException('Result is not available for this reference.');
            }

            $fetches = $intent->reference_fetches + 1;
            $maxFetches = $this->maxFetchesForIntent($intent);
            $intent->update([
                'reference_fetches' => $fetches,
                'status' => $fetches >= $maxFetches ? 'used' : 'paid',
                'used_at' => $fetches >= $maxFetches ? now() : null,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'reference_fetches' => $fetches,
                    'reference_fetches_remaining' => max(0, $maxFetches - $fetches),
                ]),
            ]);

            return [
                'intent' => $intent->fresh(['verificationRequest', 'paygoService']),
                'data' => $verification->response_data,
                'fetches_remaining' => max(0, $maxFetches - $fetches),
            ];
        });
    }

    public function normalizeNin(string $nin): string
    {
        return preg_replace('/\D+/', '', $nin);
    }

    public function resultSearchParameter(CustomerPaygoService $paygoService, array $params): string
    {
        $board = $paygoService->resultBoard();

        if (! $board) {
            return '';
        }

        return $this->resultVerificationEngine->searchParameterForBoard($board, $params);
    }

    protected function lookupLabel(CustomerPaygoService $paygoService, string $lookup): string
    {
        if ($paygoService->isResultVerification()) {
            $board = strtoupper((string) $paygoService->resultBoard());

            return trim($board.' '.$lookup);
        }

        return 'NIN ****'.substr($lookup, -4);
    }

    protected function maxFetchesFor(CustomerPaygoService $paygoService): int
    {
        if ($paygoService->isResultVerification()) {
            $paygoService->loadMissing('user.customer');

            return $paygoService->user?->customer?->paygoResultReferenceFetchLimit() ?? self::MAX_VERIFICATION_ATTEMPTS;
        }

        return self::MAX_VERIFICATION_ATTEMPTS;
    }

    protected function resolveCompletedResultVerification(PaygoVerificationIntent $intent): ?VerificationRequest
    {
        $intent->loadMissing(['paygoService', 'verificationRequest']);

        if ($intent->verificationRequest?->status === 'completed' && filled($intent->verificationRequest->response_data)) {
            return $intent->verificationRequest;
        }

        if (! $intent->paygoService?->isResultVerification()) {
            return null;
        }

        $params = $intent->payload ?? [];
        $searchParameter = $params !== []
            ? $this->resultSearchParameter($intent->paygoService, $params)
            : '';

        if (blank($searchParameter)) {
            return null;
        }

        $verification = VerificationRequest::query()
            ->where('user_id', $intent->user_id)
            ->where('verification_service_id', $intent->verification_service_id)
            ->where('search_parameter', $searchParameter)
            ->where('status', 'completed')
            ->whereNotNull('response_data')
            ->latest('id')
            ->first();

        if (! $verification) {
            return null;
        }

        if ($intent->verification_request_id !== $verification->id) {
            $intent->update([
                'verification_request_id' => $verification->id,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'completed',
                    'verification_reference' => $verification->reference,
                    'result_fetched_at' => now()->toISOString(),
                ]),
            ]);

            $intent->setRelation('verificationRequest', $verification);
        }

        return $verification;
    }

    protected function maxFetchesForIntent(PaygoVerificationIntent $intent): int
    {
        return max(1, (int) ($intent->max_fetches_snapshot ?: self::MAX_VERIFICATION_ATTEMPTS));
    }

    protected function portalContextForIntent(CustomerPaygoService $paygoService, array $context): array
    {
        $customer = $paygoService->user?->customer;

        return [
            'candidate_id' => filled($context['candidate_id'] ?? null) ? (string) $context['candidate_id'] : null,
            'portal_ref' => filled($context['portal_ref'] ?? null) ? (string) $context['portal_ref'] : null,
            'portal_state' => filled($context['state'] ?? null) ? (string) $context['state'] : null,
            'referral_code' => $customer?->referral_code,
            'callback_mode' => $paygoService->callback_mode ?? 'redirect',
            'success_url_snapshot' => $paygoService->success_url,
            'failure_url_snapshot' => $paygoService->failure_url,
            'webhook_url_snapshot' => $customer?->webhook_url,
        ];
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
