<?php

namespace App\Services\Verification;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerificationEngine
{
    protected ?string $apiKeyEnvironment = null;
    protected bool $isTestMode = false;

    /**
     * Set the API key environment (test/live).
     */
    public function setEnvironment(?ApiKey $apiKey): self
    {
        if ($apiKey) {
            $this->apiKeyEnvironment = $apiKey->environment;
            $this->isTestMode = $apiKey->environment === 'test';
        }
        return $this;
    }

    /**
     * Perform a verification request.
     */
    public function verify(
        User $user,
        VerificationService $service,
        string $searchParameter,
        string $source = 'web',
        ?string $ipAddress = null
    ): VerificationResult {
        // Determine if we should charge (test mode = free if test provider available)
        $shouldCharge = true;
        $usedTestProvider = false;

        // Get providers based on environment
        $providers = $this->getProviders($service);

        Log::info('VerificationEngine::verify', [
            'source' => $source,
            'isTestMode' => $this->isTestMode,
            'apiKeyEnvironment' => $this->apiKeyEnvironment,
            'providersCount' => $providers->count(),
        ]);

        if ($providers->isEmpty()) {
            return VerificationResult::failure('Service temporarily unavailable', 'NO_PROVIDER');
        }

        // Check if test mode and test providers exist
        if ($this->isTestMode) {
            $testProviders = $providers->where('environment', 'test');
            if ($testProviders->isNotEmpty()) {
                $providers = $testProviders;
                $shouldCharge = false;
                $usedTestProvider = true;
            }
        }

        // Get the price for this service
        $price = $user->getPriceForService($service);

        Log::info('VerificationEngine::verify charging', [
            'shouldCharge' => $shouldCharge,
            'price' => $price,
            'userId' => $user->id,
        ]);

        // Handle wallet debit atomically with proper locking
        $verificationRequest = null;
        $transaction = null;
        $wallet = null;

        try {
            // Use DB transaction with locking to prevent race conditions
            $result = DB::transaction(function () use ($user, $service, $searchParameter, $shouldCharge, $price, $source, $ipAddress, &$verificationRequest, &$transaction, &$wallet) {
                // Get wallet with lock INSIDE the transaction
                $wallet = \App\Models\Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                // Check balance only if charging (with locked wallet)
                if ($shouldCharge && (!$wallet || !$wallet->hasSufficientFunds($price))) {
                    return ['error' => 'INSUFFICIENT_FUNDS'];
                }

                // Create verification request record
                $verificationRequest = VerificationRequest::create([
                    'user_id' => $user->id,
                    'verification_service_id' => $service->id,
                    'reference' => VerificationRequest::generateReference(),
                    'search_parameter' => $searchParameter,
                    'amount_charged' => $shouldCharge ? $price : 0,
                    'status' => 'processing',
                    'source' => $source,
                    'ip_address' => $ipAddress,
                ]);

                // Debit wallet only if charging (wallet is already locked)
                if ($shouldCharge && $price > 0 && $wallet) {
                    $balanceBefore = $wallet->balance;
                    $bonusBalanceBefore = $wallet->bonus_balance;
                    $originalAmount = $price;
                    $amount = $price;

                    // First deduct from bonus balance if available
                    $bonusDeduction = 0;
                    if ($wallet->bonus_balance > 0) {
                        $bonusDeduction = min($wallet->bonus_balance, $amount);
                        $wallet->bonus_balance -= $bonusDeduction;
                        $amount -= $bonusDeduction;
                    }

                    // Then deduct remaining from main balance
                    $wallet->balance -= $amount;
                    $wallet->save();

                    // Create transaction record
                    $transaction = $wallet->transactions()->create([
                        'user_id' => $wallet->user_id,
                        'reference' => \App\Models\Transaction::generateReference(),
                        'type' => 'debit',
                        'category' => 'verification',
                        'amount' => $originalAmount,
                        'balance_before' => $balanceBefore,
                        'bonus_balance_before' => $bonusBalanceBefore,
                        'balance_after' => $wallet->balance,
                        'bonus_balance_after' => $wallet->bonus_balance,
                        'description' => "Verification: {$service->name} - {$searchParameter}",
                        'metadata' => [
                            'verification_request_id' => $verificationRequest->id,
                            'bonus_deducted' => $bonusDeduction,
                            'main_deducted' => $amount,
                            'original_amount' => $originalAmount,
                        ],
                        'status' => 'completed',
                    ]);

                    $verificationRequest->update(['transaction_id' => $transaction->id]);
                }

                return ['success' => true];
            });

            // Check if transaction returned an error
            if (isset($result['error'])) {
                return VerificationResult::failure('Insufficient wallet balance', $result['error']);
            }
        } catch (\Exception $e) {
            Log::error('VerificationEngine debit failed: ' . $e->getMessage());
            return VerificationResult::failure('Payment processing failed: ' . $e->getMessage(), 'PAYMENT_ERROR');
        }

        // Safety check - should never happen
        if (!$verificationRequest) {
            return VerificationResult::failure('Failed to create verification request', 'INTERNAL_ERROR');
        }

        // Try providers in order
        $result = $this->tryProviders($providers, $searchParameter, $user, $verificationRequest, $usedTestProvider);

        if ($result->isSuccessful()) {
            return $result;
        }

        // All providers failed - check if we should refund
        // Do NOT refund for "not found" results (404 status codes or "not found" messages)
        // These are valid verification results indicating the data doesn't exist
        $shouldRefund = $this->shouldRefundOnFailure($result);

        if ($transaction && $transaction->amount > 0 && $wallet && $shouldRefund) {
            $this->refundAndFail($verificationRequest, $wallet, (float) $transaction->amount, 'All providers failed');
        } else {
            $verificationRequest->markAsFailed($result->getErrorMessage() ?? 'All providers failed');
        }

        return $result;
    }

    /**
     * Determine if we should refund on failure.
     * Do NOT refund for "not found" results - these are valid verification outcomes.
     */
    protected function shouldRefundOnFailure(VerificationResult $result): bool
    {
        $errorMessage = strtolower($result->getErrorMessage() ?? '');
        $errorCode = strtolower($result->errorCode ?? '');

        // List of patterns that indicate "not found" - should NOT be refunded
        $noRefundPatterns = [
            'not found',
            'no record',
            'record not found',
            'records not found',
            'does not exist',
            'invalid',
            'no data',
            'no result',
        ];

        // List of status codes that indicate "not found" - should NOT be refunded
        $noRefundCodes = ['404', 'not_found', 'no_record', 'invalid_id'];

        // Check error message for "not found" patterns
        foreach ($noRefundPatterns as $pattern) {
            if (str_contains($errorMessage, $pattern)) {
                Log::info("Not refunding - error message contains '{$pattern}': {$result->getErrorMessage()}");
                return false;
            }
        }

        // Check error code
        foreach ($noRefundCodes as $code) {
            if ($errorCode === $code || str_contains($errorCode, $code)) {
                Log::info("Not refunding - error code matches '{$code}': {$result->errorCode}");
                return false;
            }
        }

        // Should refund for other failures (network errors, provider down, etc.)
        return true;
    }

    /**
     * Get providers based on environment with caching.
     */
    protected function getProviders(VerificationService $service)
    {
        $cacheKey = "service_providers:{$service->id}";

        return Cache::remember($cacheKey, 60, function () use ($service) {
            return $service->activeProviders()->orderBy('priority')->get();
        });
    }

    /**
     * Try providers in the correct order based on environment.
     */
    protected function tryProviders($providers, string $searchParameter, User $user, VerificationRequest $verificationRequest, bool $tryTestFirst): VerificationResult
    {
        // Sort providers: test first if in test mode, otherwise live first
        $sortedProviders = $providers->sortBy(function ($provider) use ($tryTestFirst) {
            $envPriority = ($tryTestFirst && $provider->environment === 'test') ? 0 :
                          (!$tryTestFirst && $provider->environment === 'live' ? 0 : 1);
            return [$envPriority, $provider->priority];
        });

        foreach ($sortedProviders as $provider) {
            $result = $this->callProvider($provider, $searchParameter, $user, $verificationRequest);

            if ($result->isSuccessful()) {
                $isSandbox = $provider->environment === 'test';
                $data = $result->getData();
                $data['_sandbox'] = $isSandbox;

                $verificationRequest->update([
                    'service_provider_id' => $provider->id,
                    'response_data' => $data,
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                //Log::info('Verification successful: ' , [$result]);
                return VerificationResult::success($data, $result->responseTime, $provider->id);
            }
        }

        if($result){
            return $result;
        }
        return VerificationResult::failure('All providers failed', 'PROVIDER_ERROR');
    }

    /**
     * Call a specific provider.
     */
    protected function callProvider(
        ServiceProvider $provider,
        string $searchParameter,
        User $user,
        VerificationRequest $verificationRequest
    ): VerificationResult {
        $startTime = microtime(true);

   

        try {
            $headers = array_merge(
                $provider->request_headers ?? [],
                $provider->buildAuthHeaders()
            );

            $body = $provider->buildRequestBody($searchParameter);
            $url = $provider->full_url;

            // Log outbound request (async would be better for production)
            $apiLog = ApiLog::create([
                'user_id' => $user->id,
                'verification_request_id' => $verificationRequest->id,
                'direction' => 'outbound',
                'endpoint' => $url,
                'method' => $provider->http_method,
                'request_headers' => $this->sanitizeHeaders($headers),
                'request_body' => $body,
                'ip_address' => request()->ip(),
            ]);

            // Make the HTTP request with optimized settings
            $response = Http::timeout($provider->timeout)
                ->connectTimeout(5) // Fast connection timeout
                ->withHeaders($headers)
                ->send($provider->http_method, $url, ['json' => $body]);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            // Update API log with response
            $apiLog->update([
                'response_status' => $response->status(),
                'response_body' => $response->json(),
                'response_time' => $responseTime,
            ]);

            if ($response->successful()) {
                Log::info('Response received successfully' );
                $datajson = $response->json();

                // Check for "not found" conditions in the response body
                // These are valid verification results - the record simply doesn't exist
                $statusCode = $datajson['statusCode'] ?? $datajson['status_code'] ?? null;
                $message = $datajson['message'] ?? '';
                $messageStr = is_array($message) ? json_encode($message) : (string) $message;

                if ($statusCode === '404' || $statusCode === 404 ||
                    stripos($messageStr, 'not found') !== false ||
                    stripos($messageStr, 'no record') !== false) {
                    Log::info('Record not found (valid result, no refund): ' . $messageStr);
                    return VerificationResult::failure(
                        'Record not found: ' . $messageStr,
                        '404_NOT_FOUND',
                        $responseTime
                    );
                }

                if(isset($datajson['response_code']) && $datajson['response_code'] == '00'){
                    $datajson = ["data"=>$datajson];
                    $mappedData = $this->mapResponse($datajson, $provider->response_mapping ?? []);
                    return VerificationResult::success($mappedData, $responseTime, $provider->id);
                }
                $mappedData = $this->mapResponse($response->json(), $provider->response_mapping ?? []);
                return VerificationResult::success($mappedData, $responseTime, $provider->id);
            }
            $message = $response->json()['message'] ?? 'Unknown error';
            return VerificationResult::failure(
                'Provider returned error: ' . (is_array($message) ? json_encode($message) : $message),
                'PROVIDER_ERROR',
                $responseTime
            );
        } catch (\Exception $e) {
            Log::error($e);
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            return VerificationResult::failure($e->getMessage(), 'EXCEPTION', $responseTime);
        }
    }

    /**
     * Generate mock response for test providers (instant response).
     */
    protected function getMockResponse(ServiceProvider $provider, string $searchParameter, VerificationRequest $_verificationRequest): VerificationResult
    {
        $service = $provider->verificationService;

        $mockData = match ($service->slug) {
            'nin' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'middle_name' => 'Smith',
                'date_of_birth' => '1990-05-15',
                'gender' => 'Male',
                'phone' => '08012345678',
                'nin' => $searchParameter,
                'photo' => 'https://ui-avatars.com/api/?name=John+Doe&size=200',
            ],
            'bvn' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'middle_name' => 'Smith',
                'date_of_birth' => '1990-05-15',
                'phone' => '08012345678',
                'bvn' => $searchParameter,
                'bank_name' => 'Test Bank',
            ],
            'cac' => [
                'company_name' => 'Test Company Limited',
                'rc_number' => $searchParameter,
                'registration_date' => '2015-03-20',
                'company_type' => 'Private Limited Company',
                'address' => '123 Business Street, Lagos',
                'status' => 'Active',
            ],
            default => [
                'verified' => true,
                'id' => $searchParameter,
                'message' => 'Verification successful',
            ],
        };

        return VerificationResult::success($mockData, 50, $provider->id); // 50ms mock response time
    }

    /**
     * Map provider response to our standard format.
     */
    protected function mapResponse(array $response, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $ourKey => $providerPath) {
            $mapped[$ourKey] = Arr::get($response, $providerPath);
        }

        // Include raw response as well
        $mapped['_raw'] = $response;

        return $mapped;
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveKeys = ['authorization', 'x-api-key', 'api-key', 'token'];

        return collect($headers)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return '***REDACTED***';
            }
            return $value;
        })->toArray();
    }

    /**
     * Refund user and mark request as failed.
     */
   /**
 * Refund user and mark request as failed.
 */
    protected function refundAndFail(
        VerificationRequest $request,
        $wallet,
        float $amount,
        string $errorMessage
    ): void {
        // Prevent double refunds - check if already refunded
        if ($request->status === 'failed') {
            Log::warning("Attempted double refund for verification: {$request->reference}");
            return;
        }

        // Use DB transaction to ensure atomicity
        DB::transaction(function () use ($request, $wallet, $amount, $errorMessage) {
        // Lock the verification request to prevent race conditions
        $lockedRequest = VerificationRequest::where('id', $request->id)
            ->lockForUpdate()
            ->firstOrFail();
            
        // Check if already failed to prevent double processing
        if ($lockedRequest->status === 'failed') {
            return;
        }

        // Check if a refund transaction already exists for this request
        $existingRefund = \App\Models\Transaction::where('category', 'refund')
            ->where('metadata->verification_request_id', $lockedRequest->id)
            ->lockForUpdate()
            ->exists();

        if ($existingRefund) {
            Log::warning("Refund already exists for verification: {$lockedRequest->reference}");
            $lockedRequest->markAsFailed($errorMessage);
            return;
        }

        try {
            // Refund the user
            $wallet->credit(
                $amount,
                'refund',
                "Refund for failed verification: {$lockedRequest->reference}",
                [
                    'verification_request_id' => $lockedRequest->id,
                    'original_amount' => $amount,
                    'reason' => $errorMessage,
                ]
            );

            // Update the request status
            $lockedRequest->markAsFailed($errorMessage);
            
            Log::info("Successfully refunded {$amount} for verification: {$lockedRequest->reference}");
            
        } catch (\Exception $e) {
            Log::error("Failed to process refund for verification {$lockedRequest->reference}: " . $e->getMessage());
            throw $e; // Re-throw to trigger rollback
        }
    });
}
}
