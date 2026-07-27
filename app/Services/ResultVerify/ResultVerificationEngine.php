<?php

namespace App\Services\ResultVerify;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Models\Wallet;
use App\Services\Verification\VerificationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ResultVerificationEngine
{
    protected ?string $apiKeyEnvironment = null;

    protected bool $isTestMode = false;

    protected ?Branch $activeBranch = null;

    public function __construct(
        protected ResultFactory $factory
    ) {}

    public function setEnvironment(?ApiKey $apiKey): self
    {
        if ($apiKey) {
            $this->apiKeyEnvironment = $apiKey->environment;
            $this->isTestMode = $apiKey->environment === 'test';
            $this->activeBranch = $apiKey->branch;
        }

        return $this;
    }

    public function serviceForBoard(string $board): ?VerificationService
    {
        return $this->serviceForBoardAction($board, 'fetch');
    }

    public function serviceForBoardAction(string $board, string $action): ?VerificationService
    {
        return VerificationService::query()
            ->where('slug', $this->serviceSlug($board, $action))
            ->first();
    }

    public function formData(
        User $user,
        string $board,
        string $source = 'api',
        ?string $ipAddress = null,
        ?Branch $branch = null,
    ): VerificationResult {
        $branch ??= $this->activeBranch;
        $board = strtolower($board);

        try {
            $resultGate = $this->factory->create($board);
        } catch (InvalidArgumentException $exception) {
            return VerificationResult::failure($exception->getMessage(), 'UNSUPPORTED_RESULT_BOARD');
        }

        if (! $this->userCanAccessResultFetch($user)) {
            return VerificationResult::failure('Result board verification is not enabled for this account.', 'RESULT_FETCH_DISABLED');
        }

        $service = $this->serviceForBoardAction($board, 'form');
        if (! $service || ! $service->is_active) {
            return VerificationResult::failure('Result form service is not available', 'SERVICE_UNAVAILABLE');
        }

        $data = [
            'board' => strtoupper($board),
            'fields' => $resultGate->formFields(),
            '_sandbox' => $this->isTestMode,
        ];

        $request = null;
        $transaction = null;
        $wallet = null;
        $payment = $this->createChargedRequest(
            user: $user,
            branch: $branch,
            service: $service,
            board: $board,
            searchParameter: strtoupper($board).'-FORM',
            requestData: ['board' => $board, 'action' => 'form'],
            shouldCharge: ! $this->isTestMode,
            source: $source,
            ipAddress: $ipAddress,
            verificationRequest: $request,
            transaction: $transaction,
            wallet: $wallet,
        );

        if (($payment['error'] ?? null) === 'INSUFFICIENT_FUNDS') {
            return VerificationResult::failure('Insufficient wallet balance', 'INSUFFICIENT_FUNDS');
        }

        if (! $request) {
            return VerificationResult::failure('Failed to create result form request', 'INTERNAL_ERROR');
        }

        $request->markAsCompleted($data);

        return VerificationResult::success($data, 0);
    }

    public function verify(
        User $user,
        string $board,
        array $params,
        string $source = 'api',
        ?string $ipAddress = null,
        ?Branch $branch = null,
        bool $chargeWallet = true,
        ?float $amountCharged = null,
    ): VerificationResult {
        $branch ??= $this->activeBranch;
        $board = strtolower($board);

        try {
            $resultGate = $this->factory->create($board);
        } catch (InvalidArgumentException $exception) {
            return VerificationResult::failure($exception->getMessage(), 'UNSUPPORTED_RESULT_BOARD');
        }

        if (! $this->userCanAccessResultFetch($user)) {
            return VerificationResult::failure('Result board verification is not enabled for this account.', 'RESULT_FETCH_DISABLED');
        }

        $missingFields = $this->missingRequiredFields($resultGate, $params);
        if ($missingFields !== []) {
            return VerificationResult::failure(
                'Missing required result verification fields: '.implode(', ', $missingFields),
                'VALIDATION_ERROR'
            );
        }

        $service = $this->serviceForBoardAction($board, 'fetch');
        if (! $service || ! $service->is_active) {
            return VerificationResult::failure('Result fetch service is not available', 'SERVICE_UNAVAILABLE');
        }

        $searchParameter = $this->searchParameter($board, $params);
        if ($searchParameter === '') {
            return VerificationResult::failure('Examination number is required', 'VALIDATION_ERROR');
        }

        $price = $amountCharged ?? (float) $user->getPriceForService($service);
        $shouldCharge = $chargeWallet && ! $this->isTestMode;
        $recordedAmount = $shouldCharge ? $price : ($chargeWallet ? 0 : $price);
        $verificationRequest = null;
        $transaction = null;
        $wallet = null;

        try {
            $paymentResult = $this->createChargedRequest(
                user: $user,
                branch: $branch,
                service: $service,
                board: $board,
                searchParameter: $searchParameter,
                requestData: [
                    'board' => $board,
                    'action' => 'fetch',
                    'parameters' => $this->sanitizeParams($params),
                ],
                shouldCharge: $shouldCharge,
                source: $source,
                ipAddress: $ipAddress,
                verificationRequest: $verificationRequest,
                transaction: $transaction,
                wallet: $wallet,
                amount: $price,
                recordedAmount: $recordedAmount,
            );
        } catch (Throwable $exception) {
            Log::error('Result verification payment processing failed', [
                'board' => $board,
                'message' => $exception->getMessage(),
            ]);

            return VerificationResult::failure('Payment processing failed: '.$exception->getMessage(), 'PAYMENT_ERROR');
        }

        if (($paymentResult['error'] ?? null) === 'INSUFFICIENT_FUNDS') {
            return VerificationResult::failure('Insufficient wallet balance', 'INSUFFICIENT_FUNDS');
        }

        if (! $verificationRequest) {
            return VerificationResult::failure('Failed to create result verification request', 'INTERNAL_ERROR');
        }

        if ($this->isTestMode) {
            $data = $this->mockResult($board, $params);
            $verificationRequest->markAsCompleted($data);

            return VerificationResult::success($data, 50);
        }

        $startTime = microtime(true);
        $apiLog = ApiLog::create([
            'user_id' => $user->id,
            'branch_id' => $branch?->id,
            'verification_request_id' => $verificationRequest->id,
            'direction' => 'outbound',
            'endpoint' => "result-board:{$board}",
            'method' => 'POST',
            'request_headers' => [],
            'request_body' => $this->sanitizeParams($params),
            'ip_address' => request()?->ip(),
        ]);

        try {
            $rawResponse = $resultGate->fetchResult($params);
            $parsed = $resultGate->parseResult($rawResponse);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $apiLog->update([
                'response_status' => ($parsed['status'] ?? null) === 'success' ? 200 : 400,
                'response_body' => $this->sanitizeParsedResponse($parsed),
                'response_time' => $responseTime,
            ]);

            if (($parsed['status'] ?? null) === 'success') {
                $data = $this->formatSuccessData($board, $parsed);
                $verificationRequest->markAsCompleted($data);

                return VerificationResult::success($data, $responseTime);
            }

            $errorCode = (string) ($parsed['code'] ?? 'UNKNOWN_ERROR');
            $errorMessage = (string) ($parsed['message'] ?? 'Result verification failed.');

            if ($transaction && $wallet && $this->shouldRefund($errorCode, $errorMessage)) {
                $this->refundAndFail($verificationRequest, $wallet, (float) $transaction->amount, $errorMessage, $transaction);
            } else {
                $verificationRequest->markAsFailed($errorMessage);
            }

            return VerificationResult::failure($errorMessage, $errorCode, $responseTime);
        } catch (Throwable $exception) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $apiLog->update([
                'response_status' => 500,
                'response_body' => ['message' => $exception->getMessage()],
                'response_time' => $responseTime,
            ]);

            Log::error('Result verification failed', [
                'board' => $board,
                'reference' => $verificationRequest->reference,
                'message' => $exception->getMessage(),
            ]);

            if ($transaction && $wallet) {
                $this->refundAndFail($verificationRequest, $wallet, (float) $transaction->amount, $exception->getMessage(), $transaction);
            } else {
                $verificationRequest->markAsFailed($exception->getMessage());
            }

            return VerificationResult::failure($exception->getMessage(), 'EXCEPTION', $responseTime);
        }
    }

    public function serviceSlug(string $board, string $action = 'fetch'): string
    {
        return strtolower($board).'-result-'.strtolower($action);
    }

    public function searchParameterForBoard(string $board, array $params): string
    {
        return $this->searchParameter($board, $params);
    }

    protected function createChargedRequest(
        User $user,
        ?Branch $branch,
        VerificationService $service,
        string $board,
        string $searchParameter,
        array $requestData,
        bool $shouldCharge,
        string $source,
        ?string $ipAddress,
        ?VerificationRequest &$verificationRequest,
        ?Transaction &$transaction,
        ?Wallet &$wallet,
        ?float $amount = null,
        ?float $recordedAmount = null,
    ): array {
        $price = $amount ?? (float) $user->getPriceForService($service);
        $chargedAmount = $recordedAmount ?? ($shouldCharge ? $price : 0);
        $targetWallet = $branch?->wallet ?? $user->wallet;

        return DB::transaction(function () use (
            $user,
            $branch,
            $service,
            $board,
            $searchParameter,
            $requestData,
            $shouldCharge,
            $price,
            $chargedAmount,
            $source,
            $ipAddress,
            $targetWallet,
            &$verificationRequest,
            &$transaction,
            &$wallet,
        ) {
            $wallet = Wallet::query()
                ->whereKey($targetWallet?->id)
                ->lockForUpdate()
                ->first();

            if ($shouldCharge && (! $wallet || ! $wallet->hasSufficientFunds($price))) {
                return [
                    'error' => 'INSUFFICIENT_FUNDS',
                    'current_balance' => $wallet?->total_balance ?? 0,
                ];
            }

            $verificationRequest = VerificationRequest::create([
                'user_id' => $user->id,
                'branch_id' => $branch?->id,
                'verification_service_id' => $service->id,
                'reference' => VerificationRequest::generateReference(),
                'search_parameter' => $searchParameter,
                'request_data' => $requestData,
                'amount_charged' => $chargedAmount,
                'status' => 'processing',
                'source' => $source,
                'ip_address' => $ipAddress,
            ]);

            if ($shouldCharge && $price > 0 && $wallet) {
                $transaction = $this->debitWallet(
                    wallet: $wallet,
                    user: $user,
                    amount: $price,
                    description: "Result verification: {$service->name} - {$searchParameter}",
                    metadata: [
                        'verification_request_id' => $verificationRequest->id,
                        'board' => $board,
                    ],
                );

                $verificationRequest->update(['transaction_id' => $transaction->id]);
            }

            return ['success' => true];
        });
    }

    protected function missingRequiredFields(ResultInterface $resultGate, array $params): array
    {
        $missing = [];

        foreach ($resultGate->formFields() as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }

            $name = (string) ($field['name'] ?? '');
            if ($name === '' || filled($params[$name] ?? null)) {
                continue;
            }

            $missing[] = $name;
        }

        return $missing;
    }

    protected function userCanAccessResultFetch(User $user): bool
    {
        return $user->hasResultFetchAccess();
    }

    protected function searchParameter(string $board, array $params): string
    {
        return match (strtolower($board)) {
            'waec' => trim((string) ($params['txtExamNumber'] ?? $params['ExamNumber'] ?? '')),
            'neco' => trim((string) ($params['reg_no'] ?? $params['exam_number'] ?? '')),
            'neco-everify', 'neco_everify', 'necoeverify' => trim((string) ($params['examno'] ?? $params['exam_number'] ?? '')),
            'nbais' => trim((string) ($params['exam_no'] ?? $params['exam_number'] ?? '')),
            'nabteb' => trim((string) ($params['candid'] ?? $params['candidate_number'] ?? '')),
            default => trim((string) ($params['exam_number'] ?? $params['reg_no'] ?? $params['txtExamNumber'] ?? '')),
        };
    }

    protected function sanitizeParams(array $params): array
    {
        $sensitiveKeys = ['pin', 'txtpin', 'token', 'bearer_token', 'api_token', 'payref', 'payment_reference', 'txtcardserialno', 'serial', 'card_serial', 'cardserialno'];

        return collect($params)
            ->reject(fn ($value, string $key) => in_array($key, ['api_key', 'branch'], true))
            ->mapWithKeys(function ($value, string $key) use ($sensitiveKeys) {
                if (in_array(strtolower($key), $sensitiveKeys, true)) {
                    return [$key => '***REDACTED***'];
                }

                return [$key => $value];
            })
            ->toArray();
    }

    protected function sanitizeParsedResponse(array $parsed): array
    {
        if (($parsed['status'] ?? null) !== 'success') {
            return $parsed;
        }

        return [
            'status' => 'success',
            'candidate' => $parsed['candidate'] ?? null,
            'subjects_count' => count($parsed['subjects'] ?? []),
        ];
    }

    protected function formatSuccessData(string $board, array $parsed): array
    {
        $data = [
            'board' => strtoupper($board),
            'candidate' => $parsed['candidate'] ?? [],
            'subjects' => array_values($parsed['subjects'] ?? []),
            'overall' => $parsed['overall'] ?? null,
            '_sandbox' => false,
        ];

        if (isset($parsed['result']) && is_array($parsed['result'])) {
            $data['result'] = $parsed['result'];
        }

        return $data;
    }

    protected function mockResult(string $board, array $params): array
    {
        $examNumber = $this->searchParameter($board, $params);

        return [
            'board' => strtoupper($board),
            'candidate' => [
                'name' => 'Test Candidate',
                'candidate_name' => 'Test Candidate',
                'exam_number' => $examNumber,
                'exam_year' => (string) ($params['ExamYear'] ?? $params['exam_year'] ?? date('Y')),
                'exam_type' => (string) ($params['ExamType'] ?? $params['exam_type'] ?? 'SSCE'),
                'centre' => 'Sandbox Centre',
            ],
            'subjects' => [
                ['subject' => 'ENGLISH LANGUAGE', 'grade' => 'B3', 'score' => null],
                ['subject' => 'MATHEMATICS', 'grade' => 'A1', 'score' => null],
            ],
            'overall' => null,
            '_sandbox' => true,
        ];
    }

    protected function debitWallet(
        Wallet $wallet,
        User $user,
        float $amount,
        string $description,
        array $metadata,
    ): Transaction {
        $balanceBefore = $wallet->balance;
        $bonusBalanceBefore = $wallet->bonus_balance;
        $remainingAmount = $amount;
        $bonusDeduction = 0;

        if ($wallet->bonus_balance > 0) {
            $bonusDeduction = min((float) $wallet->bonus_balance, $remainingAmount);
            $wallet->bonus_balance -= $bonusDeduction;
            $remainingAmount -= $bonusDeduction;
        }

        $wallet->balance -= $remainingAmount;
        $wallet->save();

        return $wallet->transactions()->create([
            'user_id' => $user->id,
            'reference' => Transaction::generateReference(),
            'type' => 'debit',
            'category' => 'verification',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'bonus_balance_before' => $bonusBalanceBefore,
            'balance_after' => $wallet->balance,
            'bonus_balance_after' => $wallet->bonus_balance,
            'description' => $description,
            'metadata' => array_merge($metadata, [
                'bonus_deducted' => $bonusDeduction,
                'main_deducted' => $remainingAmount,
                'original_amount' => $amount,
            ]),
            'status' => 'completed',
        ]);
    }

    protected function shouldRefund(string $errorCode, string $errorMessage): bool
    {
        $errorCode = strtolower($errorCode);
        $errorMessage = strtolower($errorMessage);

        foreach (['invalid', 'not_found', 'result_not_found', 'no result', 'maximum result checks'] as $pattern) {
            if (str_contains($errorCode, $pattern) || str_contains($errorMessage, $pattern)) {
                return false;
            }
        }

        return true;
    }

    protected function refundAndFail(
        VerificationRequest $request,
        Wallet $wallet,
        float $amount,
        string $errorMessage,
        Transaction $transaction,
    ): void {
        DB::transaction(function () use ($request, $wallet, $amount, $errorMessage, $transaction) {
            $lockedRequest = VerificationRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status === 'failed') {
                return;
            }

            $existingRefund = Transaction::query()
                ->where('category', 'refund')
                ->where('metadata->verification_request_id', $lockedRequest->id)
                ->lockForUpdate()
                ->exists();

            if (! $existingRefund && $amount > 0) {
                $wallet->credit(
                    $amount,
                    'refund',
                    "Refund for failed result verification: {$lockedRequest->reference}",
                    [
                        'verification_request_id' => $lockedRequest->id,
                        'refunded_transaction_id' => $transaction->id,
                        'reason' => $errorMessage,
                    ],
                );
            }

            $lockedRequest->markAsFailed($errorMessage);
        });
    }
}
