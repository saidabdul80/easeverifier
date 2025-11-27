<?php

namespace App\Services\Verification;

use App\Models\ApiLog;
use App\Models\ServiceProvider;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

class VerificationEngine
{
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
        // Check if user has sufficient balance
        $price = $user->getPriceForService($service);
        $wallet = $user->wallet;

        if (!$wallet || !$wallet->hasSufficientFunds($price)) {
            return VerificationResult::failure('Insufficient wallet balance', 'INSUFFICIENT_FUNDS');
        }

        // Create verification request record
        $verificationRequest = VerificationRequest::create([
            'user_id' => $user->id,
            'verification_service_id' => $service->id,
            'reference' => VerificationRequest::generateReference(),
            'search_parameter' => $searchParameter,
            'amount_charged' => $price,
            'status' => 'processing',
            'source' => $source,
            'ip_address' => $ipAddress,
        ]);

        // Debit the wallet
        $transaction = $wallet->debit(
            $price,
            'verification',
            "Verification: {$service->name} - {$searchParameter}",
            ['verification_request_id' => $verificationRequest->id]
        );

        $verificationRequest->update(['transaction_id' => $transaction->id]);

        // Get active providers ordered by priority
        $providers = $service->activeProviders()->get();

        if ($providers->isEmpty()) {
            $this->refundAndFail($verificationRequest, $wallet, $price, 'No active service providers configured');
            return VerificationResult::failure('Service temporarily unavailable', 'NO_PROVIDER');
        }

        // Try each provider until one succeeds
        foreach ($providers as $provider) {
            $result = $this->callProvider($provider, $searchParameter, $user, $verificationRequest);

            if ($result->isSuccessful()) {
                $verificationRequest->update([
                    'service_provider_id' => $provider->id,
                    'response_data' => $result->getData(),
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                return $result;
            }
        }

        // All providers failed - refund the user
        $this->refundAndFail($verificationRequest, $wallet, $price, 'All providers failed');
        return VerificationResult::failure('Verification failed. Please try again later.', 'PROVIDER_ERROR');
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

            // Log outbound request
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

            // Make the HTTP request
            $response = Http::timeout($provider->timeout)
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
                $mappedData = $this->mapResponse($response->json(), $provider->response_mapping ?? []);
                return VerificationResult::success($mappedData, $responseTime, $provider->id);
            }

            return VerificationResult::failure(
                'Provider returned error: ' . ($response->json()['message'] ?? 'Unknown error'),
                'PROVIDER_ERROR',
                $responseTime
            );
        } catch (\Exception $e) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            return VerificationResult::failure($e->getMessage(), 'EXCEPTION', $responseTime);
        }
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
