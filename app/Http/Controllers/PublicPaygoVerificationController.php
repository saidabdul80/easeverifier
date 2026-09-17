<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\User;
use App\Services\Paygo\PaygoResultCallbackService;
use App\Services\Paygo\PaygoVerificationService;
use App\Services\PaystackGatewayResolver;
use App\Services\PaystackService;
use App\Services\PaystackSplitService;
use App\Services\ResultVerify\ResultFactory;
use App\Services\ResultVerify\ResultGates\NbaisResult;
use App\Support\ResultVerificationErrorFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PublicPaygoVerificationController extends Controller
{
    public function __construct(
        protected PaygoVerificationService $paygo,
        protected PaygoResultCallbackService $resultCallbacks,
        protected PaystackService $paystack,
        protected PaystackGatewayResolver $paystackGateways,
        protected PaystackSplitService $paystackSplits,
        protected ResultFactory $resultFactory,
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
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            if ($respondWithJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'A valid NIN is required to initiate PayGo payment.',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->isMethod('get')) {
                return $this->initiateForm($paygoService, $request, 'A valid NIN is required to initiate PayGo payment.');
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

            if ($request->isMethod('get')) {
                return $this->initiateForm($paygoService, $request, $exception->getMessage());
            }

            return back()->with('error', $exception->getMessage());
        }

        $payment = $this->initializePaygoPayment($paygoService, $intent, $validated['email'] ?? $paygoService->user->email);

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

            $message = $payment['message'] ?? 'Payment gateway initialization failed.';

            if ($request->isMethod('get')) {
                return $this->initiateForm($paygoService, $request, $message);
            }

            return back()->with('error', $message);
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

    public function resultCustomer(Request $request, string $referralCode): Response
    {
        $user = User::query()
            ->whereHas('customer', fn ($query) => $query->where('referral_code', $referralCode))
            ->with('customer')
            ->firstOrFail();

        $services = $this->publicResultServicesForUser($user);
        $selectedService = null;
        $fields = [];

        if ($request->filled('service')) {
            $selectedService = $services
                ->firstWhere('public_slug', $request->string('service')->value());

            if ($selectedService) {
                $fields = $this->resultFields($selectedService);
            }
        }

        return $this->renderResultForm($user, $services, $selectedService, $fields);
    }

    public function resultService(Request $request, string $publicSlug)
    {
        $paygoService = CustomerPaygoService::with(['user.customer', 'verificationService'])
            ->where('public_slug', $publicSlug)
            ->firstOrFail();

        abort_unless($paygoService->is_active && $paygoService->isResultVerification(), 404);
        abort_unless($paygoService->user?->hasResultFetchAccess(), 403);

        $services = $this->publicResultServicesForUser($paygoService->user);
        $fields = $this->resultFields($paygoService);

        if ($request->isMethod('get')) {
            return $this->renderResultForm($paygoService->user, $services, $paygoService, $fields);
        }

        $validated = $this->validateResultPayload($request, $fields);
        $params = collect($fields)
            ->mapWithKeys(fn (array $field) => [
                (string) $field['name'] => $validated[(string) $field['name']] ?? null,
            ])
            ->filter(fn ($value) => filled($value))
            ->toArray();

        $externalReference = filled($validated['reference'] ?? null)
            ? (string) $validated['reference']
            : null;

        if ($externalReference) {

            try {
                $intent = $this->paygo->createOrFindResultReferenceIntent($paygoService, $externalReference, [
                    'params' => $params,
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'portal_context' => [
                        'candidate_id' => $validated['candidate_id'] ?? null,
                        'portal_ref' => $validated['portal_ref'] ?? null,
                        'state' => $validated['state'] ?? null,
                        'sitting' => $validated['sitting'] ?? null,
                    ],
                ], $request->ip());
            } catch (RuntimeException $exception) {
                return back()->withErrors(['result' => $exception->getMessage()])->withInput();
            }

            $intent = $this->reconcileResultReferencePayment($intent);

            if ($intent->status === 'paid' || $intent->status === 'verifying') {
                $context = [
                    'candidate_id' => $validated['candidate_id'] ?? null,
                    'portal_ref' => $validated['portal_ref'] ?? null,
                    'state' => $validated['state'] ?? null,
                    'sitting' => $validated['sitting'] ?? null,
                ];

                $result = $this->fetchResultForResponse($intent, $request, $params, $paygoService, $context);

                if (! ($result['success'] ?? false)) {
                    return $this->redirectAfterFailedPayment(
                        $intent->fresh(['paygoService']) ?? $intent,
                        $result['error'] ?? 'Result verification failed.',
                    )
                        ->withInput();
                }

                $intent = $intent->fresh(['paygoService.user.customer', 'verificationRequest']);

                $redirect = $this->resultCallbacks->redirectToConfiguredUrl($intent, true, [
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'result_status' => 'ready',
                    'attempts_remaining' => $result['attempts_remaining'] ?? max(0, (int) $intent->max_fetches_snapshot - (int) $intent->verification_attempts),
                ]);

                if ($redirect) {
                    if ($request->header('X-Inertia')) {
                        return Inertia::location($redirect->getTargetUrl());
                    }

                    return $redirect;
                }

                return $this->redirectToPaidResultWithContext($intent)
                    ->with('success', 'Payment completed. EaseVerifier is preparing this verified result.')
                    ->with('paygo_result_context', $this->paidResultFlashContext($intent));
            }

            $payment = $this->initializePaygoPayment($paygoService, $intent, $validated['email'] ?? $paygoService->user->email);

            if (! $payment['success']) {
                $intent->update([
                    'status' => 'failed',
                    'metadata' => array_merge($intent->metadata ?? [], [
                        'payment_status' => 'initialize_failed',
                        'payment_error' => $payment['message'] ?? null,
                    ]),
                ]);

                return back()->withErrors(['result' => $payment['message'] ?? 'Payment gateway initialization failed.'])->withInput();
            }

            return inertia()->location($payment['authorization_url']);
        }

        $existingIntent = $this->paygo->findPaidReusableResultIntent($paygoService, $params);
        if ($existingIntent) {
            $this->syncResultPortalContext($existingIntent, $validated);
            $existingIntent->loadMissing(['paygoService.user.customer', 'verificationRequest']);

            $redirect = $this->resultCallbacks->redirectToConfiguredUrl($existingIntent, true, [
                'status' => 'paid',
                'payment_status' => 'paid',
                'result_status' => $existingIntent->verificationRequest?->status === 'completed' ? 'ready' : 'paid',
            ]);

            if ($redirect) {
                if ($request->header('X-Inertia')) {
                    return Inertia::location($redirect->getTargetUrl());
                }

                return $redirect;
            }

            return $this->redirectToPaidResultWithContext($existingIntent);
        }

        try {
            $intent = $this->paygo->createIntent($paygoService, [
                'params' => $params,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'portal_context' => [
                    'candidate_id' => $validated['candidate_id'] ?? null,
                    'portal_ref' => $validated['portal_ref'] ?? null,
                    'state' => $validated['state'] ?? null,
                    'sitting' => $validated['sitting'] ?? null,
                ],
            ], $request->ip());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['result' => $exception->getMessage()])->withInput();
        }

        $payment = $this->initializePaygoPayment($paygoService, $intent, $validated['email'] ?? $paygoService->user->email);

        if (! $payment['success']) {
            $intent->update([
                'status' => 'failed',
                'metadata' => array_merge($intent->metadata ?? [], [
                    'payment_status' => 'initialize_failed',
                    'payment_error' => $payment['message'] ?? null,
                ]),
            ]);

            return back()->withErrors(['result' => $payment['message'] ?? 'Payment gateway initialization failed.'])->withInput();
        }

        return inertia()->location($payment['authorization_url']);
    }

    public function resultPaid(Request $request, string $reference): Response
    {
        $fetchError = $request->session()->get('error');
        $fetchErrorContext = $request->session()->get('paygo_result_context', []);
        $portalRef = $request->query('portal_ref') ? (string) $request->query('portal_ref') : null;
        $sitting = $request->query('sitting') ? (string) $request->query('sitting') : null;
        $contextualFetchError = ResultVerificationErrorFormatter::publicMessage(
            $this->contextualPaidResultError($fetchError, $fetchErrorContext, $portalRef, $sitting)
        );
        $verification = null;
        $attempt = null;
        $resultError = null;
        $resultPending = false;

        try {
            $intent = $this->paygo->displayResultByReference($reference, false);
            $attempt = $this->paygo->displayResultAttemptForContext($intent, $portalRef, $sitting);

            if ($attempt) {
                $verification = $attempt->status === 'completed' ? $attempt->verificationRequest : null;
                $resultError = ResultVerificationErrorFormatter::publicMessage(
                    $attempt->error_message ?: ($attempt->status === 'failed' ? 'Result verification failed.' : null),
                    $attempt->error_code
                );
            } elseif (! $contextualFetchError && blank($portalRef) && blank($sitting)) {
                $verification = $this->paygo->displayVerificationForResultIntent($intent);
            } elseif (! $contextualFetchError) {
                $resultError = 'Result is not available for this portal attempt yet.';
            } else {
                $resultError = $contextualFetchError;
            }

            if (! $resultError && ! $attempt && in_array($intent->status, ['paid', 'verifying'], true) && ! $verification && blank($portalRef) && blank($sitting)) {
                $this->dispatchResultFetchAfterResponse($intent, $request);
                $intent = $this->paygo->displayResultByReference($reference, false);
                $resultPending = true;
            }
        } catch (RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }

        $usage = $this->paygo->resultFetchUsage($intent);
        $displayService = $attempt?->paygoService
            ?? $this->displayPaygoServiceForResultContext($intent, filled($portalRef) || filled($sitting))
            ?? $this->paygo->displayPaygoServiceForResultIntent($intent)
            ?? $intent->paygoService;

        $pullQuery = array_filter([
            'portal_ref' => $portalRef,
            'sitting' => $sitting,
        ], fn ($value) => $value !== null && $value !== '');

        return Inertia::render('Public/Paygo/ResultPaid', [
            'paygoService' => $this->publicResultServicePayload($displayService),
            'intent' => [
                'reference' => $intent->reference,
                'status' => $intent->status,
                'lookup_label' => $attempt?->lookup_label ?? $intent->metadata['latest_lookup_label'] ?? $intent->lookup_label,
                'candidate_id' => $intent->metadata['candidate_id'] ?? null,
                'portal_ref' => $portalRef ?? $intent->metadata['portal_ref'] ?? null,
                'sitting' => $sitting ? (int) $sitting : ($intent->metadata['result_sitting'] ?? null),
                'paid_at' => $intent->paid_at,
                'fetches_used' => $usage['used'],
                'fetches_allowed' => $usage['allowed'],
                'fetches_remaining' => $usage['remaining'],
                'pull_url' => url('/api/paygo/results/'.$intent->reference).($pullQuery ? '?'.http_build_query($pullQuery) : ''),
            ],
            'verification' => $verification,
            'result' => [
                'success' => ! $resultPending && blank($resultError) && $verification?->status === 'completed',
                'pending' => $resultPending,
                'data' => ! $resultPending && blank($resultError) ? $verification?->response_data : null,
                'error' => ResultVerificationErrorFormatter::publicMessage(
                    $resultPending ? null : ($resultError ?: ($intent->metadata['error_message'] ?? $verification?->error_message))
                ),
            ],
        ]);
    }

    public function resultSchools(Request $request, string $publicSlug, NbaisResult $nbaisResult): JsonResponse
    {
        $paygoService = CustomerPaygoService::with('verificationService')
            ->where('public_slug', $publicSlug)
            ->firstOrFail();

        abort_unless($paygoService->is_active && $paygoService->resultBoard() === 'nbais', 404);

        $validated = $request->validate([
            'parent_cat' => 'required|string|max:10',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $nbaisResult->fetchSchools($validated['parent_cat']),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'error_code' => 'SCHOOL_LOOKUP_FAILED',
            ], 400);
        }
    }

    public function pullResult(Request $request, string $reference): JsonResponse
    {
        try {
            $result = $this->paygo->pullResultByReference(
                $reference,
                $request->query('portal_ref') ? (string) $request->query('portal_ref') : null,
                $request->query('sitting') ? (string) $request->query('sitting') : null
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'limit') ? 429 : 400;

            return response()->json([
                'success' => false,
                'error' => ResultVerificationErrorFormatter::publicMessage($exception->getMessage()),
                'error_code' => $status === 429 ? 'PULL_LIMIT_EXCEEDED' : 'RESULT_REFERENCE_INVALID',
            ], $status);
        }

        /** @var PaygoVerificationIntent $intent */
        $intent = $result['intent'];

        $responsePayload = [
            'success' => true,
            'status' => 200,
            'reference' => $intent->reference,
            'lookup_label' => $result['lookup_label'] ?? $intent->lookup_label,
            'candidate_id' => $intent->metadata['candidate_id'] ?? null,
            'portal_ref' => $result['portal_ref'] ?? $intent->metadata['portal_ref'] ?? null,
            'sitting' => $result['sitting'] ?? $intent->metadata['result_sitting'] ?? null,
            'data' => $result['data'],
            'fetches_remaining' => $result['fetches_remaining'],
            'served_from' => $result['served_from'] ?? 'local_cache',
        ];

        Log::info('EaseVerifier PayGo result API pull outbound', [
            'reference' => $intent->reference,
            'request_portal_ref' => $request->query('portal_ref'),
            'request_sitting' => $request->query('sitting'),
            'response_portal_ref' => $responsePayload['portal_ref'] ?? null,
            'response_sitting' => $responsePayload['sitting'] ?? null,
            'lookup_label' => $responsePayload['lookup_label'] ?? null,
            'board' => is_array($responsePayload['data'] ?? null) ? ($responsePayload['data']['board'] ?? $responsePayload['data']['exam_body'] ?? null) : null,
            'served_from' => $responsePayload['served_from'] ?? null,
            'payload' => $responsePayload,
        ]);

        return response()->json($responsePayload);
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

    protected function initializePaygoPayment(CustomerPaygoService $paygoService, PaygoVerificationIntent $intent, string $email): array
    {
        $amountInKobo = (int) round((float) $intent->amount * 100);
        $checkout = $intent->metadata['paystack_checkout'] ?? [];

        if ($intent->status === 'pending' && filled($checkout['authorization_url'] ?? null)) {
            return [
                'success' => true,
                'authorization_url' => $checkout['authorization_url'],
                'access_code' => $checkout['access_code'] ?? null,
                'reference' => $checkout['reference'] ?? $intent->reference,
                'served_from' => 'cached_checkout',
            ];
        }

        $gatewayPinned = filled($intent->paystack_environment)
            && ! ($intent->status === 'failed' && blank($checkout['authorization_url'] ?? null));
        $gateway = $gatewayPinned && $intent->paystack_gateway_owner_type === 'customer'
            ? $this->paystackGateways->forIntent($intent)
            : $this->paystackGateways->forCustomer($paygoService->user?->customer);

        if (! $gatewayPinned) {
            $intent->update([
                'paystack_gateway_account_id' => $gateway?->id,
                'paystack_gateway_owner_type' => $gateway ? 'customer' : 'system',
                'paystack_environment' => $gateway?->environment ?? $this->paystackGateways->systemEnvironment(),
                'paystack_key_fingerprint' => $gateway?->key_fingerprint ?? $this->paystackGateways->systemFingerprint(),
                'settlement_strategy' => $gateway
                    ? 'customer_gateway_system_subaccount'
                    : 'system_gateway_customer_subaccount',
            ]);
            $intent->refresh();
        }

        $paystack = $this->paystackGateways->client($gateway);

        try {
            $split = $this->paystackSplits->buildDynamicFlatSplit(
                $paygoService->user?->customer,
                $amountInKobo,
                $intent->reference,
                $gateway,
                (int) round((float) $intent->system_price_snapshot * 100),
            );
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        if ($split) {
            $intent->update([
                'metadata' => array_merge($intent->metadata ?? [], [
                    'paystack_split' => $split['metadata'],
                ]),
            ]);
        }

        Log::info('EaseVerifier PayGo Paystack split prepared', [
            'payment_reference' => $intent->reference,
            'flow_type' => $intent->flow_type,
            'is_reference_package' => $intent->isResultReferenceFlow(),
            'gateway_owner_type' => $intent->paystack_gateway_owner_type,
            'gateway_account_id' => $intent->paystack_gateway_account_id,
            'settlement_strategy' => $intent->settlement_strategy,
            'transaction_amount' => (float) $intent->amount,
            'transaction_amount_kobo' => $amountInKobo / 100,
            'system_price_snapshot' => (float) $intent->system_price_snapshot,
            'system_share_kobo' => data_get($split, 'metadata.total_split_amount_kobo') / 100,
            'customer_remainder_kobo' => data_get($split, 'metadata.main_account_remainder_kobo') / 100,
            'subaccount_code' => data_get($split, 'payment_options.subaccount'),
            'transaction_charge_kobo' => data_get($split, 'payment_options.transaction_charge'),
            'fee_bearer' => data_get($split, 'payment_options.bearer'),
            'split_applied' => (bool) data_get($split, 'metadata.applied', false),
        ]);

        $payment = $paystack->initializeTransaction(
            email: $email,
            amountInKobo: $amountInKobo,
            reference: $intent->reference,
            callbackUrl: route('paygo.callback'),
            options: $split ? $split['payment_options'] : [],
        );

        if (! ($payment['success'] ?? false) && $this->isDuplicatePaystackReferenceMessage($payment['message'] ?? null)) {
            $verifiedPayment = $paystack->verifyTransaction($intent->reference);

            if (($verifiedPayment['success'] ?? false) && ($verifiedPayment['status'] ?? null) === 'success') {
                try {
                    $this->paygo->completePayment($intent->reference, $verifiedPayment);
                } catch (RuntimeException $exception) {
                    return [
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }

                return [
                    'success' => true,
                    'authorization_url' => route('paygo.callback', ['reference' => $intent->reference]),
                    'access_code' => null,
                    'reference' => $intent->reference,
                    'served_from' => 'verified_duplicate_reference',
                ];
            }
        }

        if ($payment['success'] ?? false) {
            $intent->update([
                'metadata' => array_merge($intent->metadata ?? [], [
                    'paystack_checkout' => [
                        'authorization_url' => $payment['authorization_url'] ?? null,
                        'access_code' => $payment['access_code'] ?? null,
                        'reference' => $payment['reference'] ?? $intent->reference,
                        'amount' => (float) $intent->amount,
                        'system_price' => (float) $intent->system_price_snapshot,
                        'initialized_at' => now()->toISOString(),
                    ],
                ]),
            ]);
        }

        return $payment;
    }

    protected function isDuplicatePaystackReferenceMessage(?string $message): bool
    {
        $message = strtolower((string) $message);

        return str_contains($message, 'duplicate') && str_contains($message, 'reference');
    }

    protected function publicResultServicesForUser(User $user)
    {
        if (! $user->hasResultFetchAccess()) {
            return collect();
        }

        CustomerPaygoService::syncResultBoardSetForUser($user);

        return CustomerPaygoService::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['user.customer', 'verificationService'])
            ->whereHas('verificationService', function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('slug', 'like', '%-result-fetch');
            })
            ->orderedByResultBoard()
            ->get();
    }

    protected function renderResultForm(User $user, $services, ?CustomerPaygoService $selectedService, array $fields): Response
    {
        return Inertia::render('Public/Paygo/ResultInitiate', [
            'customer' => [
                'name' => $user->customer?->company_name ?: $user->name,
                'selector_url' => $user->customer?->referral_code
                    ? route('paygo.results.customer', $user->customer->referral_code)
                    : null,
            ],
            'services' => $services
                ->map(fn (CustomerPaygoService $service) => $this->publicResultServicePayload($service))
                ->values(),
            'paygoService' => $selectedService ? $this->publicResultServicePayload($selectedService) : null,
            'fields' => $fields,
            'prefill' => [
                'email' => request()->string('email')->value(),
                'phone' => request()->string('phone')->value(),
                'candidate_id' => request()->string('candidate_id')->value(),
                'portal_ref' => request()->string('portal_ref')->value(),
                'state' => request()->string('state')->value(),
                'sitting' => request()->string('sitting')->value(),
                'reference' => request()->string('reference')->value(),
            ],
        ]);
    }

    protected function publicResultServicePayload(CustomerPaygoService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'public_slug' => $service->public_slug,
            'price' => (float) $service->price,
            'reference_price' => (float) $service->resultReferencePrice(),
            'reference_success_limit' => 2,
            'service_name' => $service->verificationService?->name,
            'board' => strtoupper((string) $service->resultBoard()),
            'customer_name' => $service->user?->customer?->company_name ?: $service->user?->name,
            'result_url' => $service->resultUrl(),
            'selector_url' => $service->resultSelectorUrl(),
        ];
    }

    protected function displayPaygoServiceForResultContext(PaygoVerificationIntent $intent, bool $hasContext): ?CustomerPaygoService
    {
        if (! $hasContext) {
            return null;
        }

        $serviceId = (int) ($intent->metadata['latest_customer_paygo_service_id'] ?? 0);

        if ($serviceId <= 0) {
            return null;
        }

        return CustomerPaygoService::query()
            ->with(['user.customer', 'verificationService'])
            ->whereKey($serviceId)
            ->first();
    }

    protected function paidResultFlashContext(PaygoVerificationIntent $intent): array
    {
        return [
            'portal_ref' => filled($intent->metadata['portal_ref'] ?? null) ? (string) $intent->metadata['portal_ref'] : null,
            'sitting' => filled($intent->metadata['result_sitting'] ?? null) ? (int) $intent->metadata['result_sitting'] : null,
            'service_id' => filled($intent->metadata['latest_customer_paygo_service_id'] ?? null)
                ? (int) $intent->metadata['latest_customer_paygo_service_id']
                : (int) $intent->customer_paygo_service_id,
        ];
    }

    protected function dispatchResultFetchAfterResponse(
        PaygoVerificationIntent $intent,
        Request $request,
        ?array $params = null,
        ?CustomerPaygoService $paygoService = null,
        array $context = []
    ): void {
        $metadata = $intent->metadata ?? [];
        $queuedAt = isset($metadata['result_fetch_queued_at'])
            ? strtotime((string) $metadata['result_fetch_queued_at'])
            : false;
        $queueKey = hash('sha256', json_encode([
            'service_id' => $paygoService?->id ?? $intent->customer_paygo_service_id,
            'params' => $params ?? $intent->payload ?? [],
            'portal_ref' => $context['portal_ref'] ?? null,
            'sitting' => $context['sitting'] ?? null,
        ], JSON_THROW_ON_ERROR));

        if (
            ($metadata['verification_status'] ?? null) === 'queued'
            && ($metadata['result_fetch_queue_key'] ?? null) === $queueKey
            && $queuedAt
            && $queuedAt > (time() - 120)
        ) {
            return;
        }

        $queuedMetadata = [
            'verification_status' => 'queued',
            'result_fetch_queued_at' => now()->toISOString(),
            'result_fetch_queue_key' => $queueKey,
        ];

        if ($paygoService) {
            $queuedMetadata['latest_customer_paygo_service_id'] = $paygoService->id;
            $queuedMetadata['latest_verification_service_id'] = $paygoService->verification_service_id;
            $queuedMetadata['latest_board'] = $paygoService->resultBoard();
        }

        if (filled($context['portal_ref'] ?? null)) {
            $queuedMetadata['portal_ref'] = (string) $context['portal_ref'];
        }

        if (filled($context['sitting'] ?? null)) {
            $queuedMetadata['result_sitting'] = (int) $context['sitting'];
        }

        $intent->update([
            'metadata' => array_merge($metadata, $queuedMetadata),
        ]);

        $intentId = (int) $intent->id;
        $ipAddress = $request->ip();
        $serviceId = $paygoService ? (int) $paygoService->id : null;
        $paramsSnapshot = $params;
        $contextSnapshot = $context;

        app()->terminating(function () use ($intentId, $ipAddress, $serviceId, $paramsSnapshot, $contextSnapshot): void {
            $freshIntent = PaygoVerificationIntent::query()
                ->with(['paygoService.user.customer', 'verificationService', 'verificationRequest'])
                ->find($intentId);

            if (! $freshIntent || ! $freshIntent->isResultFlow()) {
                return;
            }

            $freshService = $serviceId
                ? CustomerPaygoService::query()
                    ->with(['user.customer', 'verificationService'])
                    ->find($serviceId)
                : null;

            try {
                $result = $this->paygo->fetchResultForPaidIntent(
                    $freshIntent,
                    $ipAddress,
                    $paramsSnapshot,
                    $freshService,
                    $contextSnapshot
                );

                $freshIntent = $freshIntent->fresh(['paygoService.user.customer', 'verificationRequest']);

                if ($freshIntent) {
                    $this->resultCallbacks->sendResultWebhook(
                        $freshIntent,
                        (bool) ($result['success'] ?? false),
                        $result['data'] ?? null,
                        $result['error'] ?? null,
                        $result['error_code'] ?? null,
                    );
                }
            } catch (\Throwable $exception) {
                Log::warning('Deferred PayGo result fetch failed', [
                    'intent_id' => $intentId,
                    'reference' => $freshIntent->reference ?? null,
                    'message' => $exception->getMessage(),
                ]);

                $publicMessage = ResultVerificationErrorFormatter::publicMessage($exception->getMessage());

                $freshIntent->update([
                    'status' => 'paid',
                    'metadata' => array_merge($freshIntent->metadata ?? [], [
                        'verification_status' => 'failed',
                        'error_code' => 'RESULT_FETCH_FAILED',
                        'error_message' => $publicMessage,
                        'internal_error_message' => $exception->getMessage(),
                    ]),
                ]);

                $this->resultCallbacks->sendResultWebhook(
                    $freshIntent->fresh(['paygoService.user.customer', 'verificationRequest']) ?? $freshIntent,
                    false,
                    null,
                    $publicMessage,
                    'RESULT_FETCH_FAILED',
                );
            }
        });
    }

    protected function contextualPaidResultError(mixed $error, mixed $context, ?string $portalRef, ?string $sitting): ?string
    {
        if (! filled($error)) {
            return null;
        }

        if (blank($portalRef) && blank($sitting)) {
            return (string) $error;
        }

        if (! is_array($context) || $context === []) {
            return null;
        }

        $contextPortalRef = filled($context['portal_ref'] ?? null) ? (string) $context['portal_ref'] : null;
        $contextSitting = filled($context['sitting'] ?? null) ? (int) $context['sitting'] : null;
        $requestedSitting = filled($sitting) ? (int) $sitting : null;

        if (filled($portalRef) && filled($contextPortalRef) && $portalRef !== $contextPortalRef) {
            return null;
        }

        if ($requestedSitting !== null && $contextSitting !== null && $requestedSitting !== $contextSitting) {
            return null;
        }

        return (string) $error;
    }

    protected function resultFields(CustomerPaygoService $paygoService): array
    {
        $board = $paygoService->resultBoard();
        abort_unless($board, 404);

        $fields = $this->resultFactory->create($board)->formFields();

        if ($board === 'nbais') {
            $fields = collect($fields)
                ->map(function (array $field) use ($paygoService) {
                    if (($field['name'] ?? null) === 'sub_cat') {
                        $field['options_endpoint'] = route('paygo.results.schools', $paygoService->public_slug);
                    }

                    return $field;
                })
                ->all();
        }

        return $fields;
    }

    protected function validateResultPayload(Request $request, array $fields): array
    {
        $rules = [
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'candidate_id' => 'nullable|string|max:120',
            'portal_ref' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:500',
            'sitting' => 'nullable|integer|min:1|max:10',
            'reference' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._=-]+$/'],
        ];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $rules[$name] = ($field['required'] ?? false)
                ? 'required|string|max:500'
                : 'nullable|string|max:500';
        }

        return $request->validate($rules);
    }

    protected function syncResultPortalContext(PaygoVerificationIntent $intent, array $validated): void
    {
        $metadata = [];

        if (filled($validated['candidate_id'] ?? null)) {
            $metadata['candidate_id'] = (string) $validated['candidate_id'];
        }

        if (filled($validated['portal_ref'] ?? null)) {
            $metadata['portal_ref'] = (string) $validated['portal_ref'];
        }

        if (filled($validated['state'] ?? null)) {
            $metadata['portal_state'] = (string) $validated['state'];
        }

        if (filled($validated['sitting'] ?? null)) {
            $metadata['result_sitting'] = (int) $validated['sitting'];
        }

        if ($metadata === []) {
            return;
        }

        $intent->update([
            'metadata' => array_merge($intent->metadata ?? [], $metadata),
        ]);
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

    protected function initiateForm(CustomerPaygoService $paygoService, Request $request, ?string $error = null): Response
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
                'email' => $request->string('email')->value(),
                'phone' => $request->string('phone')->value(),
            ],
            'error' => $error,
        ]);
    }

    public function callback(Request $request)
    {
        $reference = $request->query('reference');
        abort_unless($reference, 404);

        $intent = \App\Models\PaygoVerificationIntent::with('paygoService')
            ->where('reference', $reference)
            ->firstOrFail();

        try {
            $gateway = $this->paystackGateways->forIntent($intent);
            $payment = $this->paystackGateways->client($gateway)->verifyTransaction($reference);
        } catch (Throwable $exception) {
            return $this->redirectAfterFailedPayment($intent, $exception->getMessage());
        }
        if (! $payment['success'] || ($payment['status'] ?? null) !== 'success') {
            if (! $intent->paid_at && ! in_array($intent->status, ['paid', 'verifying', 'used'], true)) {
                $intent->update(['status' => 'failed']);
            }

            if ($intent->isResultFlow()) {
                $this->resultCallbacks->sendResultWebhook(
                    $intent->fresh(['paygoService.user.customer']),
                    false,
                    null,
                    'Payment was not completed.',
                    'PAYMENT_NOT_COMPLETED',
                );
            }

            return $this->redirectAfterFailedPayment($intent, 'Payment was not completed.');
        }

        try {
            $intent = $this->paygo->completePayment($reference, $payment);
        } catch (RuntimeException $exception) {
            return $this->redirectAfterFailedPayment($intent, $exception->getMessage());
        }

        if ($intent->isResultFlow()) {
            $result = $this->fetchResultForResponse($intent, $request);

            if (! ($result['success'] ?? false)) {
                return $this->redirectAfterFailedPayment(
                    $intent->fresh(['paygoService']) ?? $intent,
                    $result['error'] ?? 'Result verification failed.',
                );
            }

            $intent = $intent->fresh(['paygoService.user.customer', 'verificationRequest']);

            $redirect = $this->resultCallbacks->redirectToConfiguredUrl($intent, true, [
                'status' => 'paid',
                'payment_status' => 'paid',
                'result_status' => 'ready',
                'attempts_remaining' => $result['attempts_remaining'] ?? null,
            ]);

            if ($redirect) {
                return $redirect;
            }

            return $this->redirectToPaidResultWithContext($intent)
                ->with('success', 'Payment successful. EaseVerifier is preparing this verified result.')
                ->with('paygo_result_context', $this->paidResultFlashContext($intent));
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

    protected function redirectToPaidResultWithContext(PaygoVerificationIntent $intent): RedirectResponse
    {
        $params = ['reference' => $intent->reference];

        if (filled($intent->metadata['portal_ref'] ?? null)) {
            $params['portal_ref'] = (string) $intent->metadata['portal_ref'];
        }

        if (filled($intent->metadata['result_sitting'] ?? null)) {
            $params['sitting'] = (int) $intent->metadata['result_sitting'];
        }

        return redirect()->route('paygo.results.paid', $params);
    }

    protected function redirectAfterPayment(\App\Models\PaygoVerificationIntent $intent, bool $success, string $message)
    {
        $intent->loadMissing('paygoService');
        $url = $success
            ? ($intent->paygoService?->success_url ?? $intent->metadata['success_url_snapshot'] ?? null)
            : ($intent->paygoService?->failure_url ?? $intent->metadata['failure_url_snapshot'] ?? null);

        if ($url) {
            $query = array_filter([
                'reference' => $intent->reference,
                'status' => $success ? 'paid' : 'failed',
                'candidate_id' => $intent->metadata['candidate_id'] ?? null,
                'portal_ref' => $intent->metadata['portal_ref'] ?? null,
                'sitting' => $intent->metadata['result_sitting'] ?? null,
                'state' => $intent->metadata['portal_state'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').http_build_query($query));
        }

        return redirect()->route('home')->with($success ? 'success' : 'error', $message);
    }

    protected function redirectAfterFailedPayment(PaygoVerificationIntent $intent, string $message): RedirectResponse
    {
        if (! $intent->isResultFlow()) {
            return $this->redirectAfterPayment($intent, false, $message);
        }

        $intent = $intent->fresh() ?? $intent;
        $paygoService = $this->displayPaygoServiceForResultContext($intent, true);
        $paygoService ??= $intent->paygoService()
            ->with(['user.customer', 'verificationService'])
            ->first();

        if (! $paygoService) {
            return redirect()->route('home')->with('error', $message);
        }

        $query = array_filter([
            'reference' => $intent->isResultReferenceFlow() ? $intent->reference : null,
            'candidate_id' => $intent->metadata['candidate_id'] ?? null,
            'portal_ref' => $intent->metadata['portal_ref'] ?? null,
            'sitting' => $intent->metadata['result_sitting'] ?? null,
            'state' => $intent->metadata['portal_state'] ?? null,
            'email' => $intent->metadata['buyer_email'] ?? null,
            'phone' => $intent->buyer_phone,
        ], fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('paygo.results.service', array_merge([
                'publicSlug' => $paygoService->public_slug,
            ], $query))
            ->with('error', ResultVerificationErrorFormatter::publicMessage($message));
    }

    protected function fetchResultForResponse(
        PaygoVerificationIntent $intent,
        Request $request,
        ?array $params = null,
        ?CustomerPaygoService $paygoService = null,
        array $context = [],
    ): array {
        try {
            $result = $this->paygo->fetchResultForPaidIntent(
                $intent,
                $request->ip(),
                $params,
                $paygoService,
                $context,
            );
        } catch (Throwable $exception) {
            $publicMessage = ResultVerificationErrorFormatter::publicMessage($exception->getMessage());
            $intent->update([
                'status' => 'paid',
                'metadata' => array_merge($intent->metadata ?? [], [
                    'verification_status' => 'failed',
                    'error_code' => 'RESULT_FETCH_FAILED',
                    'error_message' => $publicMessage,
                    'internal_error_message' => $exception->getMessage(),
                ]),
            ]);

            $result = [
                'success' => false,
                'error' => $publicMessage,
                'error_code' => 'RESULT_FETCH_FAILED',
            ];
        }

        $freshIntent = $intent->fresh(['paygoService.user.customer', 'verificationRequest']) ?? $intent;
        $this->resultCallbacks->sendResultWebhook(
            $freshIntent,
            (bool) ($result['success'] ?? false),
            $result['data'] ?? null,
            $result['error'] ?? null,
            $result['error_code'] ?? null,
        );

        return $result;
    }

    protected function reconcileResultReferencePayment(PaygoVerificationIntent $intent): PaygoVerificationIntent
    {
        if (
            ! $intent->isResultReferenceFlow()
            || ! in_array($intent->status, ['pending', 'failed'], true)
            || blank(data_get($intent->metadata, 'paystack_checkout.authorization_url'))
        ) {
            return $intent;
        }

        try {
            $gateway = $this->paystackGateways->forIntent($intent);
            $payment = $this->paystackGateways->client($gateway)->verifyTransaction($intent->reference);

            if (($payment['success'] ?? false) && ($payment['status'] ?? null) === 'success') {
                return $this->paygo->completePayment($intent->reference, $payment);
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to reconcile PayGo result reference payment before checkout reuse.', [
                'reference' => $intent->reference,
                'message' => $exception->getMessage(),
            ]);
        }

        return $intent->fresh(['paygoService']);
    }
}
