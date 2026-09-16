<?php

namespace App\Services\Paygo;

use App\Models\Customer;
use App\Models\CustomerPaygoService;
use App\Models\CustomerPaystackSplitLedger;
use App\Models\PaygoResultAttempt;
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

    private const RESULT_REFERENCE_SUCCESS_LIMIT = 2;

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

    public function createOrFindResultReferenceIntent(
        CustomerPaygoService $paygoService,
        string $reference,
        array $data,
        ?string $ipAddress = null
    ): PaygoVerificationIntent {
        $paygoService->loadMissing(['user.wallet', 'user.customer', 'verificationService']);

        if (! $paygoService->is_active || ! $paygoService->verificationService?->is_active || ! $paygoService->isResultVerification()) {
            throw new RuntimeException('This pay-on-the-go result service is not available.');
        }

        $reference = $this->normalizeExternalReference($reference);
        $params = $data['params'] ?? [];
        $lookup = $this->resultSearchParameter($paygoService, $params);

        if ($lookup === '') {
            throw new RuntimeException($this->resultLookupMissingMessage($paygoService));
        }

        $systemPrice = $paygoService->resultReferenceSystemPrice();
        $publicPrice = $paygoService->resultReferencePrice();

        if ($publicPrice <= $systemPrice) {
            throw new RuntimeException('This reference PayGo price is below the allowed minimum.');
        }

        $portalContext = $this->portalContextForIntent($paygoService, $data['portal_context'] ?? []);

        return DB::transaction(function () use ($paygoService, $reference, $data, $ipAddress, $params, $lookup, $portalContext, $publicPrice, $systemPrice) {
            $existing = PaygoVerificationIntent::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (
                    (int) $existing->user_id !== (int) $paygoService->user_id
                    || ! $existing->isResultReferenceFlow()
                ) {
                    throw new RuntimeException('This portal reference is already attached to a different PayGo payment.');
                }

                $updates = [
                    'payload' => $params,
                    'lookup_hash' => PaygoVerificationIntent::hashLookup($paygoService->id.':'.$lookup),
                    'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                    'buyer_phone' => $data['phone'] ?? $existing->buyer_phone,
                    'metadata' => array_merge($existing->metadata ?? [], $portalContext, [
                        'buyer_email' => $data['email'] ?? data_get($existing->metadata, 'buyer_email'),
                        'latest_lookup_label' => $this->lookupLabel($paygoService, $lookup),
                        'latest_customer_paygo_service_id' => $paygoService->id,
                        'latest_verification_service_id' => $paygoService->verification_service_id,
                        'latest_board' => $paygoService->resultBoard(),
                    ]),
                ];

                if (in_array($existing->status, ['pending', 'failed'], true)) {
                    $updates = array_merge($updates, [
                        'customer_paygo_service_id' => $paygoService->id,
                        'verification_service_id' => $paygoService->verification_service_id,
                        'amount' => $publicPrice,
                        'system_price_snapshot' => $systemPrice,
                    ]);
                }

                $existing->update($updates);

                return $existing->fresh(['paygoService']);
            }

            return PaygoVerificationIntent::create([
                'customer_paygo_service_id' => $paygoService->id,
                'user_id' => $paygoService->user_id,
                'verification_service_id' => $paygoService->verification_service_id,
                'flow_type' => 'result_reference',
                'reference' => $reference,
                'nin_hash' => null,
                'lookup_hash' => PaygoVerificationIntent::hashLookup($paygoService->id.':'.$lookup),
                'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                'payload' => $params,
                'amount' => $publicPrice,
                'system_price_snapshot' => $systemPrice,
                'status' => 'pending',
                'verification_attempts' => 0,
                'max_fetches_snapshot' => self::RESULT_REFERENCE_SUCCESS_LIMIT,
                'reference_fetches' => 0,
                'buyer_phone' => $data['phone'] ?? null,
                'expires_at' => now()->addHours(24),
                'metadata' => array_merge([
                    'flow_type' => 'result_reference',
                    'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                    'buyer_email' => $data['email'] ?? null,
                    'initiated_ip' => $ipAddress,
                    'payment_gateway' => 'paystack',
                    'payment_status' => 'pending',
                    'external_reference' => $reference,
                    'latest_customer_paygo_service_id' => $paygoService->id,
                    'latest_verification_service_id' => $paygoService->verification_service_id,
                    'latest_board' => $paygoService->resultBoard(),
                    'successful_result_attempts' => 0,
                    'attempts_remaining' => self::RESULT_REFERENCE_SUCCESS_LIMIT,
                ], $portalContext),
            ])->fresh(['paygoService']);
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

    public function findPaidReusableResultIntent(CustomerPaygoService $paygoService, array $params): ?PaygoVerificationIntent
    {
        if (! $paygoService->isResultVerification()) {
            return null;
        }

        $lookup = $this->resultSearchParameter($paygoService, $params);

        if ($lookup === '') {
            return null;
        }

        return PaygoVerificationIntent::query()
            ->where('customer_paygo_service_id', $paygoService->id)
            ->where('flow_type', 'result')
            ->where('lookup_hash', PaygoVerificationIntent::hashLookup($paygoService->id.':'.$lookup))
            ->where('status', 'paid')
            ->whereColumn('reference_fetches', '<', 'max_fetches_snapshot')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->get()
            ->first(fn (PaygoVerificationIntent $intent) => $this->resultPayloadMatches($intent->payload ?? [], $params));
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
                $splitApplied = (bool) data_get($intent->metadata, 'paystack_split.applied');
                $paygoWallet = null;
                $earningTransaction = null;

                if (! $splitApplied) {
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
                }

                $intent->update([
                    'status' => 'paid',
                    'transaction_id' => null,
                    'paid_at' => $paymentData['paid_at'] ?? now(),
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'payment_gateway' => 'paystack',
                        'payment_status' => 'success',
                        'payment_reference' => $paymentData['reference'] ?? $reference,
                        'payment_channel' => $paymentData['channel'] ?? null,
                        'paygo_wallet_id' => $paygoWallet?->id,
                        'paygo_wallet_transaction_id' => $earningTransaction?->id,
                        'paygo_earning' => $splitApplied ? 0 : $margin,
                        'paygo_wallet_credit_skipped' => $splitApplied,
                        'system_price' => (float) $intent->system_price_snapshot,
                    ]),
                ]);

                if ($splitApplied) {
                    $this->recordPaystackSplitLedger($intent->fresh(), $paymentData, $reference);
                }
            }

            return $intent->fresh(['paygoService', 'transaction']);
        });
    }

    private function recordPaystackSplitLedger(PaygoVerificationIntent $intent, array $paymentData, string $reference): void
    {
        $split = $intent->metadata['paystack_split'] ?? null;

        if (! is_array($split) || empty($split['subaccounts']) || ! is_array($split['subaccounts'])) {
            return;
        }

        $customerId = Customer::where('user_id', $intent->user_id)->value('id');

        if (! $customerId) {
            return;
        }

        foreach ($split['subaccounts'] as $subaccount) {
            $subaccountCode = $subaccount['subaccount_code'] ?? null;

            if (! $subaccountCode) {
                continue;
            }

            CustomerPaystackSplitLedger::updateOrCreate(
                [
                    'paygo_verification_intent_id' => $intent->id,
                    'subaccount_code' => $subaccountCode,
                ],
                [
                    'customer_id' => $customerId,
                    'user_id' => $intent->user_id,
                    'payment_reference' => $paymentData['reference'] ?? $reference,
                    'split_reference' => $split['reference'] ?? null,
                    'subaccount_label' => $subaccount['label'] ?? null,
                    'flat_amount' => (float) ($subaccount['flat_amount'] ?? 0),
                    'flat_amount_kobo' => (int) ($subaccount['share'] ?? 0),
                    'transaction_amount' => (float) $intent->amount,
                    'main_account_remainder' => (float) ($split['main_account_remainder'] ?? 0),
                    'status' => 'completed',
                    'paid_at' => $paymentData['paid_at'] ?? $intent->paid_at ?? now(),
                    'metadata' => [
                        'paygo_reference' => $intent->reference,
                        'customer_paygo_service_id' => $intent->customer_paygo_service_id,
                        'verification_service_id' => $intent->verification_service_id,
                        'split_type' => $split['type'] ?? null,
                        'bearer_type' => $split['bearer_type'] ?? null,
                    ],
                ],
            );
        }
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

    public function fetchResultForPaidIntent(
        PaygoVerificationIntent $intent,
        ?string $ipAddress = null,
        ?array $params = null,
        ?CustomerPaygoService $paygoService = null,
        array $context = []
    ): array {
        $intent->loadMissing(['paygoService.user.customer', 'verificationService', 'verificationRequest']);

        if (! $intent->isResultFlow() || ! $intent->paygoService?->isResultVerification()) {
            throw new RuntimeException('This PayGo payment is not for result verification.');
        }

        if ($intent->isResultReferenceFlow()) {
            return $this->fetchResultForReferenceIntent($intent, $params ?? ($intent->payload ?? []), $ipAddress, $paygoService, $context);
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
            $intent->update([
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'completed',
                    'verification_reference' => $completedVerification->reference,
                    'result_served_from' => 'local_cache',
                    'result_last_served_at' => now()->toISOString(),
                ]),
            ]);

            return [
                'success' => true,
                'data' => $completedVerification->response_data,
                'response_time' => 0,
                'verification' => $completedVerification,
                'served_from' => 'local_cache',
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
            $intent = $intent->fresh(['paygoService', 'verificationRequest']);
            $completedVerification = $this->resolveCompletedResultVerification($intent);

            if ($completedVerification) {
                $verification = $completedVerification;
            }
        }

        if ($result->isSuccessful()) {
            $intent->update([
                'status' => 'paid',
                'verification_request_id' => $verification?->id,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'completed',
                    'verification_reference' => $verification?->reference,
                    'result_fetched_at' => now()->toISOString(),
                    'result_served_from' => 'provider_fetch',
                ]),
            ]);

            return [
                'success' => true,
                'data' => $result->getData(),
                'response_time' => $result->responseTime,
                'verification' => $verification,
                'served_from' => 'provider_fetch',
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

    protected function fetchResultForReferenceIntent(
        PaygoVerificationIntent $intent,
        array $params,
        ?string $ipAddress = null,
        ?CustomerPaygoService $paygoService = null,
        array $context = []
    ): array
    {
        $intent->loadMissing(['paygoService.user.customer', 'verificationService']);
        $paygoService ??= $intent->paygoService;

        if (! $paygoService) {
            throw new RuntimeException('This pay-on-the-go result service is not available for this payment reference.');
        }

        $paygoService->loadMissing(['user.customer', 'verificationService']);

        if (
            ! $paygoService->is_active
            || ! $paygoService->verificationService?->is_active
            || ! $paygoService->isResultVerification()
            || (int) $paygoService->user_id !== (int) $intent->user_id
        ) {
            throw new RuntimeException('This pay-on-the-go result service is not available for this payment reference.');
        }

        if ($params === []) {
            throw new RuntimeException($this->resultLookupMissingMessage($paygoService));
        }

        $board = $paygoService->resultBoard();
        if (! $board) {
            throw new RuntimeException('Unable to determine the result board for this PayGo service.');
        }

        $lookup = $this->resultSearchParameter($paygoService, $params);
        if ($lookup === '') {
            throw new RuntimeException($this->resultLookupMissingMessage($paygoService));
        }

        $portalRef = filled($context['portal_ref'] ?? null)
            ? (string) $context['portal_ref']
            : (filled($intent->metadata['portal_ref'] ?? null) ? (string) $intent->metadata['portal_ref'] : null);
        $sitting = $this->normalizeResultSitting($context['sitting'] ?? ($intent->metadata['result_sitting'] ?? null));

        $prepared = DB::transaction(function () use ($intent, $params, $lookup, $paygoService, $portalRef, $sitting) {
            $lockedIntent = PaygoVerificationIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIntent->expires_at && now()->greaterThan($lockedIntent->expires_at) && $lockedIntent->status !== 'used') {
                $lockedIntent->update(['status' => 'expired']);
                throw new RuntimeException('This result verification payment has expired.');
            }

            if (! in_array($lockedIntent->status, ['paid', 'verifying'], true)) {
                throw new RuntimeException('Payment has not been completed for this result verification.');
            }

            $lookupHash = PaygoVerificationIntent::hashLookup($paygoService->id.':'.$lookup);
            $completedAttempt = $this->findCompletedResultAttempt($lockedIntent, $lookupHash, $params);

            if ($completedAttempt) {
                $this->attachPortalContextToResultAttempt($completedAttempt, $portalRef, $sitting);

                return [
                    'intent' => $lockedIntent,
                    'attempt' => $completedAttempt->load('verificationRequest'),
                    'cached' => true,
                ];
            }

            $successfulAttempts = $this->successfulResultAttemptCount($lockedIntent);
            $maxAttempts = $this->maxFetchesForIntent($lockedIntent);

            if ($successfulAttempts >= $maxAttempts) {
                throw new RuntimeException('This result payment reference has reached its successful fetch limit.');
            }

            $attempt = PaygoResultAttempt::create([
                'paygo_verification_intent_id' => $lockedIntent->id,
                'customer_paygo_service_id' => $paygoService->id,
                'verification_service_id' => $paygoService->verification_service_id,
                'lookup_hash' => $lookupHash,
                'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                'payload' => $params,
                'status' => 'processing',
                'attempt_number' => $successfulAttempts + 1,
                'success_counted' => false,
                'metadata' => array_filter([
                    'started_at' => now()->toISOString(),
                    'portal_ref' => $portalRef,
                    'portal_refs' => $portalRef ? [$portalRef] : null,
                    'sitting' => $sitting,
                ]),
            ]);

            $lockedIntent->update([
                'status' => 'verifying',
                'payload' => $params,
                'lookup_hash' => $lookupHash,
                'lookup_label' => $this->lookupLabel($paygoService, $lookup),
                'metadata' => array_merge($lockedIntent->metadata ?? [], [
                    'verification_status' => 'processing',
                    'latest_lookup_label' => $this->lookupLabel($paygoService, $lookup),
                    'latest_customer_paygo_service_id' => $paygoService->id,
                    'latest_verification_service_id' => $paygoService->verification_service_id,
                    'latest_board' => $paygoService->resultBoard(),
                    'result_sitting' => $sitting,
                ]),
            ]);

            return [
                'intent' => $lockedIntent->fresh(['paygoService.user.customer', 'verificationService']),
                'attempt' => $attempt,
                'cached' => false,
            ];
        });

        /** @var PaygoResultAttempt $attempt */
        $attempt = $prepared['attempt'];

        if ($prepared['cached']) {
            $verification = $attempt->verificationRequest;

            return [
                'success' => true,
                'data' => $verification?->response_data,
                'response_time' => 0,
                'verification' => $verification,
                'served_from' => 'reference_attempt_cache',
                'attempts_remaining' => max(0, $this->maxFetchesForIntent($intent) - $this->successfulResultAttemptCount($intent)),
            ];
        }

        $result = $this->resultVerificationEngine->verify(
            user: $intent->user,
            board: $board,
            params: $params,
            source: 'paygo',
            ipAddress: $ipAddress,
            chargeWallet: false,
            amountCharged: $this->resultReferencePerAttemptSystemPrice($intent),
        );

        $verification = VerificationRequest::query()
            ->where('user_id', $intent->user_id)
            ->where('verification_service_id', $paygoService->verification_service_id)
            ->where('search_parameter', $lookup)
            ->latest('id')
            ->first();

        if ($result->isSuccessful()) {
            $freshIntent = DB::transaction(function () use ($intent, $attempt, $verification, $result) {
                $lockedIntent = PaygoVerificationIntent::query()
                    ->whereKey($intent->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $attempt->update([
                    'verification_request_id' => $verification?->id,
                    'status' => 'completed',
                    'success_counted' => true,
                    'metadata' => array_merge($attempt->metadata ?? [], [
                        'completed_at' => now()->toISOString(),
                        'response_time' => $result->responseTime,
                    ]),
                ]);

                $successfulAttempts = $this->successfulResultAttemptCount($lockedIntent);
                $maxAttempts = $this->maxFetchesForIntent($lockedIntent);

                $lockedIntent->update([
                    'status' => 'paid',
                    'verification_attempts' => $successfulAttempts,
                    'verification_request_id' => $verification?->id,
                    'metadata' => array_merge($lockedIntent->metadata ?? [], [
                        'verification_status' => 'completed',
                        'verification_reference' => $verification?->reference,
                        'result_fetched_at' => now()->toISOString(),
                        'result_served_from' => 'provider_fetch',
                        'successful_result_attempts' => $successfulAttempts,
                        'attempts_remaining' => max(0, $maxAttempts - $successfulAttempts),
                    ]),
                ]);

                return $lockedIntent->fresh(['paygoService', 'verificationRequest']);
            });

            return [
                'success' => true,
                'data' => $result->getData(),
                'response_time' => $result->responseTime,
                'verification' => $verification,
                'intent' => $freshIntent,
                'served_from' => 'provider_fetch',
                'attempts_remaining' => max(0, $this->maxFetchesForIntent($freshIntent) - $freshIntent->verification_attempts),
            ];
        }

        $attempt->update([
            'verification_request_id' => $verification?->id,
            'status' => 'failed',
            'success_counted' => false,
            'error_code' => $result->errorCode,
            'error_message' => $result->getErrorMessage(),
            'metadata' => array_merge($attempt->metadata ?? [], [
                'failed_at' => now()->toISOString(),
                'response_time' => $result->responseTime,
            ]),
        ]);

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
            'attempts_remaining' => max(0, $this->maxFetchesForIntent($intent) - $this->successfulResultAttemptCount($intent)),
        ];
    }

    public function displayResultByReference(string $reference, bool $resolveVerification = true): PaygoVerificationIntent
    {
        $intent = PaygoVerificationIntent::query()
            ->with(['paygoService.user.customer', 'verificationService', 'verificationRequest', 'resultAttempts.verificationRequest'])
            ->where('reference', $reference)
            ->firstOrFail();

        if (! $intent->isResultFlow()) {
            throw new RuntimeException('This reference is not for a PayGo result verification.');
        }

        if (! $resolveVerification) {
            return $intent;
        }

        $verification = $this->displayVerificationForResultIntent($intent);

        if ($verification) {
            $intent->setRelation('verificationRequest', $verification);
        }

        return $intent;
    }

    public function displayVerificationForResultIntent(PaygoVerificationIntent $intent): ?VerificationRequest
    {
        if (! $intent->isResultFlow()) {
            return null;
        }

        if ($intent->isResultReferenceFlow()) {
            $attempt = $this->latestCompletedResultAttempt($intent);

            if ($attempt?->verificationRequest && $intent->verification_request_id !== $attempt->verificationRequest->id) {
                $intent->update([
                    'verification_request_id' => $attempt->verificationRequest->id,
                    'lookup_label' => $attempt->lookup_label,
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'verification_status' => 'completed',
                        'verification_reference' => $attempt->verificationRequest->reference,
                        'latest_lookup_label' => $attempt->lookup_label,
                        'latest_customer_paygo_service_id' => $attempt->customer_paygo_service_id,
                        'latest_verification_service_id' => $attempt->verification_service_id,
                    ]),
                ]);

                $intent->verification_request_id = $attempt->verificationRequest->id;
                $intent->lookup_label = $attempt->lookup_label;
            }

            return $attempt?->verificationRequest;
        }

        return $this->resolveCompletedResultVerification($intent);
    }

    public function displayPaygoServiceForResultIntent(PaygoVerificationIntent $intent): ?CustomerPaygoService
    {
        if ($intent->isResultReferenceFlow()) {
            $attempt = $this->latestCompletedResultAttempt($intent);

            if ($attempt?->paygoService) {
                return $attempt->paygoService;
            }
        }

        return $intent->paygoService;
    }

    public function displayResultAttemptForContext(
        PaygoVerificationIntent $intent,
        ?string $portalRef = null,
        ?string $sitting = null
    ): ?PaygoResultAttempt {
        if (! $intent->isResultReferenceFlow()) {
            return null;
        }

        $attempts = PaygoResultAttempt::query()
            ->with(['verificationRequest', 'paygoService.user.customer', 'paygoService.verificationService'])
            ->where('paygo_verification_intent_id', $intent->id)
            ->latest('id')
            ->get();

        if (filled($portalRef)) {
            $attempt = $attempts->first(fn (PaygoResultAttempt $attempt) => $this->resultAttemptMatchesPortalRef($attempt, (string) $portalRef));

            if ($attempt) {
                return $attempt;
            }
        }

        $normalizedSitting = $this->normalizeResultSitting($sitting);
        if ($normalizedSitting !== null) {
            return $attempts->first(fn (PaygoResultAttempt $attempt) => $this->resultAttemptSitting($attempt) === $normalizedSitting);
        }

        return null;
    }

    public function resultFetchUsage(PaygoVerificationIntent $intent): array
    {
        $used = $intent->isResultReferenceFlow()
            ? $this->successfulResultAttemptCount($intent)
            : (int) $intent->reference_fetches;

        $allowed = $this->maxFetchesForIntent($intent);

        return [
            'used' => $used,
            'allowed' => $allowed,
            'remaining' => max(0, $allowed - $used),
        ];
    }

    public function pullResultByReference(string $reference, ?string $portalRef = null, ?string $sitting = null): array
    {
        return DB::transaction(function () use ($reference, $portalRef, $sitting) {
            $intent = PaygoVerificationIntent::query()
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $intent || ! $intent->isResultFlow()) {
                throw new RuntimeException('Result reference was not found.');
            }

            $intent->loadMissing(['verificationRequest', 'paygoService']);

            if ($intent->isResultReferenceFlow()) {
                if (! in_array($intent->status, ['paid', 'verifying'], true)) {
                    throw new RuntimeException('Result payment has not been completed.');
                }

                if (filled($portalRef) || $this->normalizeResultSitting($sitting) !== null) {
                    $contextAttempt = $this->displayResultAttemptForContext($intent, $portalRef, $sitting);

                    if ($contextAttempt) {
                        if ($contextAttempt->status === 'completed' && $contextAttempt->verificationRequest) {
                            $successfulAttempts = $this->successfulResultAttemptCount($intent);

                            return [
                                'intent' => $intent->fresh(['verificationRequest', 'paygoService']),
                                'data' => $contextAttempt->verificationRequest->response_data,
                                'lookup_label' => $contextAttempt->lookup_label,
                                'portal_ref' => $this->resultAttemptPortalRef($contextAttempt) ?? $portalRef,
                                'sitting' => $this->resultAttemptSitting($contextAttempt) ?? $this->normalizeResultSitting($sitting),
                                'fetches_remaining' => max(0, $this->maxFetchesForIntent($intent) - $successfulAttempts),
                                'served_from' => 'reference_attempt_cache',
                            ];
                        }

                        if ($contextAttempt->status === 'failed') {
                            throw new RuntimeException($contextAttempt->error_message ?: 'Result verification failed for this attempt.');
                        }

                        throw new RuntimeException('Result is not available for this portal attempt yet.');
                    }
                }

                $attempt = $this->latestCompletedResultAttempt($intent, $portalRef, $sitting);

                if (! $attempt?->verificationRequest) {
                    throw new RuntimeException($portalRef
                        ? 'Result is not available for this portal attempt yet.'
                        : 'Result is not available for this reference.');
                }

                $successfulAttempts = $this->successfulResultAttemptCount($intent);

                return [
                    'intent' => $intent->fresh(['verificationRequest', 'paygoService']),
                    'data' => $attempt->verificationRequest->response_data,
                    'lookup_label' => $attempt->lookup_label,
                    'portal_ref' => $this->resultAttemptPortalRef($attempt) ?? $portalRef,
                    'sitting' => $this->resultAttemptSitting($attempt) ?? $this->normalizeResultSitting($sitting),
                    'fetches_remaining' => max(0, $this->maxFetchesForIntent($intent) - $successfulAttempts),
                    'served_from' => 'reference_attempt_cache',
                ];
            }

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
                'lookup_label' => $intent->lookup_label,
                'fetches_remaining' => max(0, $maxFetches - $fetches),
                'served_from' => 'local_cache',
            ];
        });
    }

    public function normalizeNin(string $nin): string
    {
        return preg_replace('/\D+/', '', $nin);
    }

    public function normalizeExternalReference(string $reference): string
    {
        $reference = trim($reference);

        if ($reference === '' || strlen($reference) > 80 || ! preg_match('/^[A-Za-z0-9._=-]+$/', $reference)) {
            throw new RuntimeException('A valid portal payment reference is required.');
        }

        return $reference;
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

    protected function resultReferencePerAttemptSystemPrice(PaygoVerificationIntent $intent): float
    {
        return round((float) $intent->system_price_snapshot / $this->maxFetchesForIntent($intent), 2);
    }

    protected function successfulResultAttemptCount(PaygoVerificationIntent $intent): int
    {
        return PaygoResultAttempt::query()
            ->where('paygo_verification_intent_id', $intent->id)
            ->where('success_counted', true)
            ->count();
    }

    protected function findCompletedResultAttempt(
        PaygoVerificationIntent $intent,
        string $lookupHash,
        array $params
    ): ?PaygoResultAttempt {
        return PaygoResultAttempt::query()
            ->where('paygo_verification_intent_id', $intent->id)
            ->where('lookup_hash', $lookupHash)
            ->where('status', 'completed')
            ->where('success_counted', true)
            ->latest('id')
            ->get()
            ->first(fn (PaygoResultAttempt $attempt) => $this->resultPayloadMatches($attempt->payload ?? [], $params));
    }

    protected function latestCompletedResultAttempt(PaygoVerificationIntent $intent, ?string $portalRef = null, ?string $sitting = null): ?PaygoResultAttempt
    {
        $attempts = PaygoResultAttempt::query()
            ->with(['verificationRequest', 'paygoService.user.customer', 'paygoService.verificationService'])
            ->where('paygo_verification_intent_id', $intent->id)
            ->where('status', 'completed')
            ->where('success_counted', true)
            ->latest('id')
            ->get();

        if (filled($portalRef)) {
            $attempt = $attempts->first(fn (PaygoResultAttempt $attempt) => $this->resultAttemptMatchesPortalRef($attempt, (string) $portalRef));

            if ($attempt) {
                return $attempt;
            }
        }

        $normalizedSitting = $this->normalizeResultSitting($sitting);
        if ($normalizedSitting !== null) {
            return $attempts->first(fn (PaygoResultAttempt $attempt) => $this->resultAttemptSitting($attempt) === $normalizedSitting);
        }

        return $attempts->first();
    }

    protected function attachPortalContextToResultAttempt(PaygoResultAttempt $attempt, ?string $portalRef, ?int $sitting = null): void
    {
        if (blank($portalRef) && $sitting === null) {
            return;
        }

        $metadata = $attempt->metadata ?? [];
        $portalRefs = $metadata['portal_refs'] ?? [];

        if (! is_array($portalRefs)) {
            $portalRefs = filled($portalRefs) ? [(string) $portalRefs] : [];
        }

        $portalRefs[] = (string) $portalRef;
        $portalRefs = array_values(array_unique(array_filter($portalRefs)));

        $attempt->update([
            'metadata' => array_filter(array_merge($metadata, [
                'portal_ref' => filled($portalRef) ? ($metadata['portal_ref'] ?? (string) $portalRef) : ($metadata['portal_ref'] ?? null),
                'last_portal_ref' => filled($portalRef) ? (string) $portalRef : ($metadata['last_portal_ref'] ?? null),
                'portal_refs' => $portalRefs !== [] ? $portalRefs : ($metadata['portal_refs'] ?? null),
                'sitting' => $sitting ?? ($metadata['sitting'] ?? null),
            ]), fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    protected function resultAttemptMatchesPortalRef(PaygoResultAttempt $attempt, string $portalRef): bool
    {
        $metadata = $attempt->metadata ?? [];

        if (($metadata['portal_ref'] ?? null) === $portalRef || ($metadata['last_portal_ref'] ?? null) === $portalRef) {
            return true;
        }

        $portalRefs = $metadata['portal_refs'] ?? [];
        if (! is_array($portalRefs)) {
            return false;
        }

        return in_array($portalRef, array_map('strval', $portalRefs), true);
    }

    protected function resultAttemptPortalRef(PaygoResultAttempt $attempt): ?string
    {
        $metadata = $attempt->metadata ?? [];

        return filled($metadata['last_portal_ref'] ?? null)
            ? (string) $metadata['last_portal_ref']
            : (filled($metadata['portal_ref'] ?? null) ? (string) $metadata['portal_ref'] : null);
    }

    protected function resultAttemptSitting(PaygoResultAttempt $attempt): ?int
    {
        return $this->normalizeResultSitting(($attempt->metadata ?? [])['sitting'] ?? null);
    }

    protected function normalizeResultSitting(mixed $sitting): ?int
    {
        if ($sitting === null || $sitting === '') {
            return null;
        }

        $sitting = (int) $sitting;

        return $sitting > 0 ? $sitting : null;
    }

    protected function resultLookupMissingMessage(CustomerPaygoService $paygoService): string
    {
        $board = strtoupper((string) ($paygoService->resultBoard() ?: 'selected board'));

        return "We could not start {$board} result verification because the required exam details were not received. Please return to the portal and try again.";
    }

    protected function resultPayloadMatches(array $storedParams, array $submittedParams): bool
    {
        return $this->normalizedResultPayload($storedParams) === $this->normalizedResultPayload($submittedParams);
    }

    protected function normalizedResultPayload(array $params): array
    {
        $normalized = collect($params)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->toArray();

        ksort($normalized);

        return $normalized;
    }

    protected function portalContextForIntent(CustomerPaygoService $paygoService, array $context): array
    {
        $customer = $paygoService->user?->customer;

        return [
            'candidate_id' => filled($context['candidate_id'] ?? null) ? (string) $context['candidate_id'] : null,
            'portal_ref' => filled($context['portal_ref'] ?? null) ? (string) $context['portal_ref'] : null,
            'portal_state' => filled($context['state'] ?? null) ? (string) $context['state'] : null,
            'result_sitting' => $this->normalizeResultSitting($context['sitting'] ?? null),
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
