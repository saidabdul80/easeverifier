<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaygoService;
use App\Services\Paygo\PaygoVerificationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PublicPaygoVerificationController extends Controller
{
    public function __construct(
        protected PaygoVerificationService $paygo,
        protected PaystackService $paystack,
    ) {}

    public function initiate(Request $request, string $publicSlug, ?string $nin = null)
    {
        $paygoService = CustomerPaygoService::with(['user.wallet', 'verificationService'])
            ->where('public_slug', $publicSlug)
            ->firstOrFail();

        if ($nin && ! $request->filled('nin')) {
            $request->merge(['nin' => $nin]);
        }

        $respondWithJson = $this->shouldRespondWithJson($request, $paygoService);

        if ($request->isMethod('get') && ! $request->filled('nin')) {
            if ($respondWithJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'A valid NIN is required to initiate PayGo payment.',
                    'error_code' => 'NIN_REQUIRED',
                ], 422);
            }

            return $this->initiateForm($paygoService, $request);
        }

        $validator = Validator::make($request->all(), [
            'nin' => ['required', 'string', 'regex:/^\d{11}$/'],
            'phone' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            if ($respondWithJson || $request->isMethod('get')) {
                return response()->json([
                    'success' => false,
                    'error' => 'A valid NIN is required to initiate PayGo payment.',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $existingIntent = $this->paygo->findPaidUnusedIntent($paygoService, $validated['nin']);
        if ($existingIntent) {
            if ($respondWithJson) {
                return response()->json([
                    'success' => true,
                    'status' => 'already_paid',
                    'paid' => true,
                    'message' => 'Payment has already been made for this NIN.',
                ]);
            }

            return $this->alreadyPaid($paygoService, $existingIntent);
        }

        try {
            $intent = $this->paygo->createIntent($paygoService, $validated, $request->ip());
        } catch (RuntimeException $exception) {
            if ($respondWithJson) {
                return response()->json([
                    'success' => false,
                    'error' => $exception->getMessage(),
                    'error_code' => 'PAYGO_INITIATE_FAILED',
                ], 400);
            }

            return back()->with('error', $exception->getMessage());
        }

        $payment = $this->paystack->initializeTransaction(
            email: $paygoService->user->email,
            amountInKobo: (int) ((float) $intent->amount * 100),
            reference: $intent->reference,
            callbackUrl: route('paygo.callback'),
        );

        if (! $payment['success']) {
            $intent->update([
                'status' => 'failed',
                'metadata' => array_merge($intent->metadata ?? [], [
                    'payment_status' => 'initialize_failed',
                    'payment_error' => $payment['message'] ?? null,
                ]),
            ]);
            $intent->transaction?->update(['status' => 'failed']);

            if ($respondWithJson) {
                return response()->json([
                    'success' => false,
                    'error' => $payment['message'] ?? 'Payment gateway initialization failed.',
                    'error_code' => 'PAYMENT_GATEWAY_ERROR',
                ], 400);
            }

            return back()->with('error', $payment['message'] ?? 'Payment gateway initialization failed.');
        }

        if ($respondWithJson) {
            return response()->json([
                'success' => true,
                'status' => 'payment_initialized',
                'payment_gateway' => 'paystack',
                'redirect_url' => $payment['authorization_url'],
                'reference' => $intent->reference,
                'amount' => (float) $intent->amount,
                'currency' => 'NGN',
            ]);
        }

        return inertia()->location($payment['authorization_url']);
    }

    protected function shouldRespondWithJson(Request $request, CustomerPaygoService $paygoService): bool
    {
        $responseMode = strtolower((string) ($request->query('response') ?: $request->query('format')));

        if (in_array($responseMode, ['json', 'api'], true)) {
            return true;
        }

        if (in_array($responseMode, ['ui', 'redirect', 'html'], true)) {
            return false;
        }

        return $request->expectsJson() || ($paygoService->response_mode ?? 'redirect') === 'json';
    }

    protected function alreadyPaid(CustomerPaygoService $paygoService, \App\Models\PaygoVerificationIntent $intent): Response
    {
        return Inertia::render('Public/Paygo/Paid', [
            'paygoService' => [
                'name' => $paygoService->name,
                'price' => (float) $paygoService->price,
                'service_name' => $paygoService->verificationService?->name,
                'customer_name' => $paygoService->user?->customer?->company_name ?: $paygoService->user?->name,
                'verify_url' => $paygoService->verifyUrl(),
            ],
            'intent' => [
                'reference' => $intent->reference,
                'status' => $intent->status,
                'paid_at' => $intent->paid_at,
                'expires_at' => $intent->expires_at,
                'nin_last4' => $intent->metadata['nin_last4'] ?? null,
            ],
        ]);
    }

    protected function initiateForm(CustomerPaygoService $paygoService, Request $request): Response
    {
        return Inertia::render('Public/Paygo/Initiate', [
            'paygoService' => [
                'name' => $paygoService->name,
                'price' => (float) $paygoService->price,
                'is_active' => $paygoService->is_active,
                'service_name' => $paygoService->verificationService?->name,
                'customer_name' => $paygoService->user?->customer?->company_name ?: $paygoService->user?->name,
                'initiate_url' => $paygoService->initiateUrl(),
            ],
            'prefill' => [
                'nin' => $request->string('nin')->value(),
                'phone' => $request->string('phone')->value(),
            ],
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        abort_unless($reference, 404);

        $intent = \App\Models\PaygoVerificationIntent::with('paygoService')
            ->where('reference', $reference)
            ->firstOrFail();

        $payment = $this->paystack->verifyTransaction($reference);
        if (! $payment['success'] || ($payment['status'] ?? null) !== 'success') {
            $intent->update(['status' => 'failed']);

            return $this->redirectAfterPayment($intent, false, 'Payment was not completed.');
        }

        try {
            $intent = $this->paygo->completePayment($reference, $payment);
        } catch (RuntimeException $exception) {
            return $this->redirectAfterPayment($intent, false, $exception->getMessage());
        }

        return $this->redirectAfterPayment($intent, true, 'Payment successful. Your verification reference is '.$intent->reference.'.');
    }

    public function verify(Request $request, string $publicSlug, ?string $nin = null): JsonResponse
    {
        $paygoService = CustomerPaygoService::with(['user', 'verificationService'])
            ->where('public_slug', $publicSlug)
            ->first();

        if (! $paygoService || ! $paygoService->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'Pay-on-the-go service not available',
                'error_code' => 'SERVICE_UNAVAILABLE',
            ], 404);
        }

        if ($nin && ! $request->filled('nin')) {
            $request->merge(['nin' => $nin]);
        }

        if (! $request->has('consent')) {
            $request->merge(['consent' => true]);
        }

        $validated = $request->validate([
            'reference' => 'nullable|string|max:80',
            'nin' => ['required', 'string', 'regex:/^\d{11}$/'],
            'consent' => 'required|boolean',
        ]);

        if (! $validated['consent']) {
            return response()->json([
                'success' => false,
                'error' => 'Consent is required',
                'error_code' => 'CONSENT_REQUIRED',
            ], 422);
        }

        try {
            $result = $this->paygo->verifyPaidIntent($paygoService, $validated, $request->ip());
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'error_code' => 'PAYGO_PAYMENT_INVALID',
            ], 400);
        }

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $result['data'],
                'response_time' => $result['response_time'],
                'message' => 'NIN Verified Successfully',
                'attempts_remaining' => $result['attempts_remaining'] ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
            'error_code' => $result['error_code'],
        ], 400);
    }

    protected function redirectAfterPayment(\App\Models\PaygoVerificationIntent $intent, bool $success, string $message)
    {
        $intent->loadMissing('paygoService');
        $url = $success ? $intent->paygoService?->success_url : $intent->paygoService?->failure_url;

        if ($url) {
            return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').http_build_query([
                'reference' => $intent->reference,
                'status' => $success ? 'paid' : 'failed',
            ]));
        }

        return redirect()->route('home')->with($success ? 'success' : 'error', $message);
    }
}
