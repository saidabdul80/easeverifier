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
        $wallet = $user->wallet;

        Log::info('VerificationEngine::verify charging', [
            'shouldCharge' => $shouldCharge,
            'price' => $price,
            'walletBalance' => $wallet?->balance,
        ]);

        // Check balance only if charging
        if ($shouldCharge && (!$wallet || !$wallet->hasSufficientFunds($price))) {
            return VerificationResult::failure('Insufficient wallet balance', 'INSUFFICIENT_FUNDS');
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

        // Debit wallet only if charging
        $transaction = null;
        if ($shouldCharge && $price > 0) {
            $transaction = $wallet->debit(
                $price,
                'verification',
                "Verification: {$service->name} - {$searchParameter}",
                ['verification_request_id' => $verificationRequest->id]
            );
            $verificationRequest->update(['transaction_id' => $transaction->id]);
        }

        // Try providers in order
        $result = $this->tryProviders($providers, $searchParameter, $user, $verificationRequest, $usedTestProvider);

        if ($result->isSuccessful()) {
            return $result;
        }

        // All providers failed - refund only the actual amount debited
        if ($transaction && $transaction->amount > 0) {
            $this->refundAndFail($verificationRequest, $wallet, (float) $transaction->amount, 'All providers failed');
        } else {
            $verificationRequest->markAsFailed('All providers failed');
        }

        return $result;
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

             Log::info('Response received: ' , [$response->json()]);
            if ($response->successful()) {
                $datajson = $response->json();
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
    protected function refundAndFail(
        VerificationRequest $request,
        $wallet,
        float $amount,
        string $errorMessage
    ): void {
        // Prevent double refunds - check if already refunded
        if ($request->status === 'failed') {
            \Illuminate\Support\Facades\Log::warning("Attempted double refund for verification: {$request->reference}");
            return;
        }

        // Also check if a refund transaction already exists for this request
        $existingRefund = \App\Models\Transaction::where('category', 'refund')
            ->where('metadata->verification_request_id', $request->id)
            ->exists();

        if ($existingRefund) {
            \Illuminate\Support\Facades\Log::warning("Refund already exists for verification: {$request->reference}");
            $request->markAsFailed($errorMessage);
            return;
        }

        // Refund the user
        $wallet->credit(
            $amount,
            'refund',
            "Refund for failed verification: {$request->reference}",
            ['verification_request_id' => $request->id]
        );

        // Update the request status
        $request->markAsFailed($errorMessage);
    }
}
