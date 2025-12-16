<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use App\Models\VerificationService;
use App\Services\Verification\VerificationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    public function __construct(
        protected VerificationEngine $verificationEngine
    ) {}

    /**
     * Verify NIN
     */
    public function verifyNin(Request $request): JsonResponse
    {
        return $this->performVerification($request, 'nin');
    }

    /**
     * Verify BVN
     */
    public function verifyBvn(Request $request): JsonResponse
    {
        return $this->performVerification($request, 'bvn');
    }

    /**
     * Generic verification endpoint
     */
    public function verify(Request $request, string $service): JsonResponse
    {
        return $this->performVerification($request, $service);
    }

    /**
     * Perform the verification
     */
    protected function performVerification(Request $request, string $serviceSlug): JsonResponse
    {
        $validated = $request->validate([
            'nin' => 'required|string|max:255',
            'consent' => 'required|boolean',
        ]);

        // Use caching for service lookup (faster)
        $service = VerificationService::where('slug', $serviceSlug)->first();
        if (!$service || !$service->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Service not available',
                'error_code' => 'SERVICE_UNAVAILABLE',
            ], 400);
        }

        $user = $request->user();
        $apiKey = $request->get('api_key'); // Set by ApiAuthentication middleware

        // Check for existing successful verification for the same user, service, and search parameter
        $existingVerification = VerificationRequest::where('user_id', $user->id)
            ->where('search_parameter', $validated['nin'])
            ->where('status', 'completed')
            ->whereNotNull('response_data')
            ->latest()
            ->first();

        if ($existingVerification) {
            Log::info('Returning cached verification result',[
                'reference' => $existingVerification->reference,
            ]);

            $data = $existingVerification->response_data;
            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $data,
                'response_time' => 0,
                'message' => 'NIN Verified Successfully',
                'sandbox' => $data['_sandbox'] ?? false,
                'cached' => true,
                'cached_reference' => $existingVerification->reference,
            ]);
        }

        // Set environment from API key (test vs live)
        $result = $this->verificationEngine
            ->setEnvironment($apiKey)
            ->verify(
                user: $user,
                service: $service,
                searchParameter: $validated['nin'],
                source: 'api',
                ipAddress: $request->ip()
            );

        if ($result->isSuccessful()) {
            $data = $result->getData();
            return response()->json([
                'success' => true,
                'status'=>200,
                'data' => $data,
                'response_time' => $result->responseTime,
                'message'=>'NIN Verified Successfully',
                'sandbox' => $data['_sandbox'] ?? false,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result->getErrorMessage(),
            'error_code' => $result->errorCode,
        ], 400);
    }

    /**
     * Get wallet balance
     */
    public function walletBalance(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet?->balance ?? 0,
                'bonus_balance' => $wallet?->bonus_balance ?? 0,
                'total_balance' => $wallet?->total_balance ?? 0,
                'currency' => $wallet?->currency ?? 'NGN',
            ],
        ]);
    }

    /**
     * Get verification history
     */
    public function history(Request $request): JsonResponse
    {
        $verifications = $request->user()->verificationRequests()
            ->with('verificationService:id,name,slug')
            ->when($request->service, fn($q, $s) => $q->whereHas('verificationService', fn($sq) => $sq->where('slug', $s)))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $verifications,
        ]);
    }

    /**
     * Show verification by reference
     */
    public function showByReference(Request $request, string $reference): JsonResponse
    {
        $verification = VerificationRequest::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->with('verificationService:id,name,slug')
            ->first();

        if (!$verification) {
            return response()->json([
                'success' => false,
                'error' => 'Verification not found',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $verification,
        ]);
    }

    /**
     * Get available services
     */
    public function services(): JsonResponse
    {
        $services = VerificationService::active()
            ->ordered()
            ->get(['id', 'name', 'slug', 'description', 'icon']);

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }
}

