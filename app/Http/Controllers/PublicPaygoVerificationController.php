<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaygoService;
use App\Models\PaygoVerificationIntent;
use App\Models\User;
use App\Services\Paygo\PaygoResultCallbackService;
use App\Services\Paygo\PaygoVerificationService;
use App\Services\PaystackService;
use App\Services\PaystackSplitService;
use App\Services\ResultVerify\ResultFactory;
use App\Services\ResultVerify\ResultGates\NbaisResult;
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
        protected PaygoResultCallbackService $resultCallbacks,
        protected PaystackService $paystack,
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

        $payment = $this->initializePaygoPayment($paygoService, $intent, $paygoService->user->email);

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

        try {
            $intent = $this->paygo->createIntent($paygoService, [
                'params' => $params,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'portal_context' => [
                    'candidate_id' => $validated['candidate_id'] ?? null,
                    'portal_ref' => $validated['portal_ref'] ?? null,
                    'state' => $validated['state'] ?? null,
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
        try {
            $intent = $this->paygo->displayResultByReference($reference);

            if ($intent->status === 'paid' && $intent->verificationRequest?->status !== 'completed') {
                $this->paygo->fetchResultForPaidIntent($intent, $request->ip());
                $intent = $this->paygo->displayResultByReference($reference);
            }
        } catch (RuntimeException $exception) {
            abort(404, $exception->getMessage());
        }

        return Inertia::render('Public/Paygo/ResultPaid', [
            'paygoService' => $this->publicResultServicePayload($intent->paygoService),
            'intent' => [
                'reference' => $intent->reference,
                'status' => $intent->status,
                'lookup_label' => $intent->lookup_label,
                'candidate_id' => $intent->metadata['candidate_id'] ?? null,
                'portal_ref' => $intent->metadata['portal_ref'] ?? null,
                'paid_at' => $intent->paid_at,
                'fetches_used' => $intent->reference_fetches,
                'fetches_allowed' => $intent->max_fetches_snapshot,
                'fetches_remaining' => max(0, (int) $intent->max_fetches_snapshot - (int) $intent->reference_fetches),
                'pull_url' => url('/api/paygo/results/'.$intent->reference),
            ],
            'verification' => $intent->verificationRequest,
            'result' => [
                'success' => $intent->verificationRequest?->status === 'completed',
                'data' => $intent->verificationRequest?->response_data,
                'error' => $intent->metadata['error_message'] ?? $intent->verificationRequest?->error_message,
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

    public function pullResult(string $reference): JsonResponse
    {
        try {
            $result = $this->paygo->pullResultByReference($reference);
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'limit') ? 429 : 400;

            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'error_code' => $status === 429 ? 'PULL_LIMIT_EXCEEDED' : 'RESULT_REFERENCE_INVALID',
            ], $status);
        }

        /** @var PaygoVerificationIntent $intent */
        $intent = $result['intent'];

        return response()->json([
            'success' => true,
            'status' => 200,
            'reference' => $intent->reference,
            'lookup_label' => $intent->lookup_label,
            'candidate_id' => $intent->metadata['candidate_id'] ?? null,
            'portal_ref' => $intent->metadata['portal_ref'] ?? null,
            'data' => $result['data'],
            'fetches_remaining' => $result['fetches_remaining'],
            'served_from' => $result['served_from'] ?? 'local_cache',
        ]);
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

        try {
            $split = $this->paystackSplits->buildDynamicFlatSplit(
                $paygoService->user?->customer,
                $amountInKobo,
                $intent->reference,
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

        return $this->paystack->initializeTransaction(
            email: $email,
            amountInKobo: $amountInKobo,
            reference: $intent->reference,
            callbackUrl: route('paygo.callback'),
            options: $split ? $split['payment_options'] : [],
        );
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
            'service_name' => $service->verificationService?->name,
            'board' => strtoupper((string) $service->resultBoard()),
            'customer_name' => $service->user?->customer?->company_name ?: $service->user?->name,
            'result_url' => $service->resultUrl(),
            'selector_url' => $service->resultSelectorUrl(),
        ];
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

        $payment = $this->paystack->verifyTransaction($reference);
        if (! $payment['success'] || ($payment['status'] ?? null) !== 'success') {
            $intent->update(['status' => 'failed']);

            if ($intent->isResultFlow()) {
                $this->resultCallbacks->sendResultWebhook(
                    $intent->fresh(['paygoService.user.customer']),
                    false,
                    null,
                    'Payment was not completed.',
                    'PAYMENT_NOT_COMPLETED',
                );
            }

            return $this->redirectAfterPayment($intent, false, 'Payment was not completed.');
        }

        try {
            $intent = $this->paygo->completePayment($reference, $payment);
        } catch (RuntimeException $exception) {
            return $this->redirectAfterPayment($intent, false, $exception->getMessage());
        }

        if ($intent->isResultFlow()) {
            try {
                $result = $this->paygo->fetchResultForPaidIntent($intent, $request->ip());
            } catch (RuntimeException $exception) {
                $this->resultCallbacks->sendResultWebhook(
                    $intent->fresh(['paygoService.user.customer']),
                    false,
                    null,
                    $exception->getMessage(),
                    'RESULT_FETCH_INVALID',
                );

                $redirect = $this->resultCallbacks->redirectToConfiguredUrl($intent, false, [
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'result_status' => 'failed',
                ]);

                if ($redirect) {
                    return $redirect;
                }

                return redirect()
                    ->route('paygo.results.paid', $intent->reference)
                    ->with('error', $exception->getMessage());
            }

            $intent = $intent->fresh(['paygoService.user.customer', 'verificationRequest']);

            $this->resultCallbacks->sendResultWebhook(
                $intent,
                (bool) ($result['success'] ?? false),
                $result['data'] ?? null,
                $result['error'] ?? null,
                $result['error_code'] ?? null,
            );

            $redirect = $this->resultCallbacks->redirectToConfiguredUrl($intent, (bool) ($result['success'] ?? false), [
                'status' => 'paid',
                'payment_status' => 'paid',
                'result_status' => ($result['success'] ?? false) ? 'ready' : 'failed',
            ]);

            if ($redirect) {
                return $redirect;
            }

            return redirect()
                ->route('paygo.results.paid', $intent->reference)
                ->with(
                    ($result['success'] ?? false) ? 'success' : 'error',
                    ($result['success'] ?? false)
                        ? 'Payment successful. Your result reference is '.$intent->reference.'.'
                        : ($result['error'] ?? 'Payment succeeded, but the result could not be fetched.'),
                );
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
        $url = $success
            ? ($intent->metadata['success_url_snapshot'] ?? $intent->paygoService?->success_url)
            : ($intent->metadata['failure_url_snapshot'] ?? $intent->paygoService?->failure_url);

        if ($url) {
            $query = array_filter([
                'reference' => $intent->reference,
                'status' => $success ? 'paid' : 'failed',
                'candidate_id' => $intent->metadata['candidate_id'] ?? null,
                'portal_ref' => $intent->metadata['portal_ref'] ?? null,
                'state' => $intent->metadata['portal_state'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').http_build_query($query));
        }

        return redirect()->route('home')->with($success ? 'success' : 'error', $message);
    }
}
