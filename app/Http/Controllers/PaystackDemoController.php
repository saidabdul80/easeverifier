<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;

class PaystackDemoController extends Controller
{
    private const AMOUNT_IN_KOBO = 150000;

    private const AMOUNT_IN_NAIRA = 1500;

    private const DEMO_EMAIL = 'paygo-demo@easeverifier.com';

    public function initialize(Request $request): JsonResponse
    {
        $client = $this->testClient();

        if (! $client) {
            return response()->json([
                'message' => 'Paystack test checkout is not configured.',
            ], 503);
        }

        $reference = 'DEMO_'.strtoupper(bin2hex(random_bytes(10)));
        $verificationToken = bin2hex(random_bytes(32));

        try {
            $payment = $client->initializeTransaction(
                email: self::DEMO_EMAIL,
                amountInKobo: self::AMOUNT_IN_KOBO,
                reference: $reference,
                callbackUrl: route('paygo.demo.callback'),
                options: [
                    'channels' => ['card'],
                    'metadata' => [
                        'purpose' => 'EaseVerifier PayGo documentation demo',
                    ],
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Paystack documentation demo initialization failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to open Paystack test checkout right now.',
            ], 502);
        }

        if (! ($payment['success'] ?? false) || blank($payment['authorization_url'] ?? null)) {
            return response()->json([
                'message' => $payment['message'] ?? 'Unable to initialize Paystack test checkout.',
            ], 502);
        }

        Cache::put($this->cacheKey($reference), [
            'verification_token' => hash('sha256', $verificationToken),
            'amount' => self::AMOUNT_IN_NAIRA,
            'completed' => false,
        ], now()->addMinutes(30));

        return response()->json([
            'reference' => $reference,
            'verification_token' => $verificationToken,
            'checkout_url' => $payment['authorization_url'],
            'frame_url' => URL::temporarySignedRoute(
                'paygo.demo.frame',
                now()->addMinutes(30),
                ['accessCode' => $payment['access_code']],
            ),
            'amount' => self::AMOUNT_IN_NAIRA,
            'currency' => 'NGN',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string', 'regex:/^DEMO_[A-F0-9]{20}$/'],
            'verification_token' => ['required', 'string', 'size:64'],
        ]);
        $reference = $validated['reference'];
        $cacheKey = $this->cacheKey($reference);
        $demo = Cache::get($cacheKey);

        if (! is_array($demo) || ! hash_equals(
            (string) ($demo['verification_token'] ?? ''),
            hash('sha256', $validated['verification_token']),
        )) {
            return response()->json(['message' => 'Demo payment session not found.'], 404);
        }

        if ($demo['completed'] ?? false) {
            return response()->json([
                'complete' => true,
                'status' => 'success',
                'reference' => $reference,
            ]);
        }

        $client = $this->testClient();

        if (! $client) {
            return response()->json([
                'message' => 'Paystack test checkout is not configured.',
            ], 503);
        }

        try {
            $payment = $client->verifyTransaction($reference);
        } catch (Throwable $exception) {
            Log::warning('Paystack documentation demo verification failed.', [
                'reference' => $reference,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to verify the Paystack test transaction right now.',
            ], 502);
        }

        if (! ($payment['success'] ?? false)) {
            return response()->json([
                'complete' => false,
                'status' => 'pending',
            ], 202);
        }

        if (($payment['status'] ?? null) !== 'success') {
            return response()->json([
                'complete' => false,
                'status' => $payment['status'] ?? 'pending',
            ], 202);
        }

        if (
            ($payment['reference'] ?? null) !== $reference
            || abs((float) ($payment['amount'] ?? 0) - self::AMOUNT_IN_NAIRA) > 0.001
            || ($payment['customer_email'] ?? null) !== self::DEMO_EMAIL
        ) {
            Log::warning('Paystack documentation demo verification mismatch.', [
                'reference' => $reference,
            ]);

            return response()->json([
                'message' => 'The Paystack transaction did not match this demo payment.',
            ], 422);
        }

        $demo['completed'] = true;
        Cache::put($cacheKey, $demo, now()->addMinutes(10));

        return response()->json([
            'complete' => true,
            'status' => 'success',
            'reference' => $reference,
        ]);
    }

    public function callback(): View
    {
        return view('paygo-demo-callback');
    }

    public function frame(string $accessCode): View
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $accessCode) === 1, 404);

        return view('paygo-demo-frame', [
            'accessCode' => $accessCode,
        ]);
    }

    private function testClient(): ?PaystackService
    {
        $publicKey = (string) config('services.paystack.test_public_key');
        $secretKey = (string) config('services.paystack.test_secret_key');

        if (! str_starts_with($publicKey, 'pk_test_') || ! str_starts_with($secretKey, 'sk_test_')) {
            return null;
        }

        return PaystackService::withCredentials($secretKey, $publicKey);
    }

    private function cacheKey(string $reference): string
    {
        return 'paystack_demo:'.hash('sha256', $reference);
    }
}
